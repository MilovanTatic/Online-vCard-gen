<?php
/**
 * Payment Service Class
 *
 * Handles payment processing operations for the NovaBanka IPG plugin.
 * Implements payment flow according to IPG integration guide.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use WC_Order;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\MessageHandler;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;

/**
 * Handles payment processing operations.
 */
class PaymentService {
	/**
	 * API Handler instance.
	 *
	 * @var APIHandler
	 */
	private $api_handler;

	/**
	 * Message Handler instance.
	 *
	 * @var MessageHandler
	 */
	private $message_handler;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Supported currency codes mapping.
	 *
	 * @var array<string, string>
	 */
	private const CURRENCY_CODES = array(
		'EUR' => '978',
		'USD' => '840',
		'GBP' => '826',
		'BAM' => '977',
	);

	/**
	 * Constructor.
	 *
	 * @param APIHandler     $api_handler     API Handler instance.
	 * @param MessageHandler $message_handler Message Handler instance.
	 * @param Logger         $logger          Logger instance.
	 */
	public function __construct(
		APIHandler $api_handler,
		MessageHandler $message_handler,
		Logger $logger
	) {
		$this->api_handler     = $api_handler;
		$this->message_handler = $message_handler;
		$this->logger          = $logger;
	}

	/**
	 * Process payment for an order.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $settings Gateway settings.
	 * @return array Payment processing result.
	 * @throws NovaBankaIPGException When payment processing fails.
	 */
	public function process_payment( int $order_id, array $settings ): array {
		$order        = $this->get_order( $order_id );
		$payment_data = $this->prepare_payment_data( $order, $settings );

		try {
			$response = $this->initiate_payment( $payment_data );
			return $this->handle_payment_response( $order, $response );
		} catch ( \Exception $e ) {
			$this->logger->log_error_and_throw(
				'Payment processing failed',
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Check if payment status indicates success.
	 *
	 * @param string $status Payment status from IPG.
	 * @return bool
	 */
	public function is_payment_successful( string $status ): bool {
		return in_array( $status, array( 'CAPTURED', 'APPROVED' ), true );
	}

	/**
	 * Get order object.
	 *
	 * @param int $order_id Order ID.
	 * @return WC_Order
	 * @throws NovaBankaIPGException When order is invalid.
	 */
	private function get_order( int $order_id ): WC_Order {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			$this->logger->log_error_and_throw(
				'Invalid order ID',
				array( 'order_id' => $order_id ),
				'ORDER_NOT_FOUND'
			);
		}
		return $order;
	}

	/**
	 * Prepare payment data for API request.
	 *
	 * @param WC_Order $order    Order object.
	 * @param array    $settings Gateway settings.
	 * @return array
	 * @throws NovaBankaIPGException When currency is not supported.
	 */
	private function prepare_payment_data( WC_Order $order, array $settings ): array {
		$currency = $order->get_currency();
		if ( ! isset( self::CURRENCY_CODES[ $currency ] ) ) {
			$this->logger->log_error_and_throw(
				sprintf( 'Unsupported currency: %s', $currency ),
				array( 'currency' => $currency ),
				'INVALID_CURRENCY'
			);
		}

		return array(
			'merchantid'   => $settings['merchant_id'],
			'password'     => $settings['merchant_password'],
			'amt'          => SharedUtilities::format_amount( $order->get_total() ),
			'currencycode' => self::CURRENCY_CODES[ $currency ],
			'trackid'      => $order->get_id(),
			'customerid'   => $order->get_customer_id(),
			'udf1'         => $order->get_billing_email(),
			'udf2'         => $order->get_billing_phone(),
			'udf3'         => wp_json_encode(
				array(
					'billing'  => $order->get_address( 'billing' ),
					'shipping' => $order->get_address( 'shipping' ),
				)
			),
		);
	}

	/**
	 * Initiate payment with IPG.
	 *
	 * @param array $payment_data Payment data.
	 * @return array
	 * @throws NovaBankaIPGException When API request fails.
	 */
	private function initiate_payment( array $payment_data ): array {
		$response = $this->api_handler->send_request( 'initiate_payment', $payment_data );

		if ( is_wp_error( $response ) ) {
			$this->logger->log_error_and_throw(
				'API request failed',
				array( 'error' => $response->get_error_message() ),
				'API_ERROR'
			);
		}

		return $this->validate_payment_response( $response );
	}

	/**
	 * Validate payment response from IPG.
	 *
	 * @param array $response API response data.
	 * @return array
	 * @throws NovaBankaIPGException When response is invalid.
	 */
	private function validate_payment_response( array $response ): array {
		if ( empty( $response['browserRedirectionURL'] ) ) {
			$this->logger->log_error_and_throw(
				'Missing redirect URL in response',
				array( 'response' => $response ),
				'INVALID_RESPONSE'
			);
		}

		return $response;
	}

	/**
	 * Handle payment response.
	 *
	 * @param WC_Order $order    Order object.
	 * @param array    $response Payment response data.
	 * @return array
	 */
	private function handle_payment_response( WC_Order $order, array $response ): array {
		if ( ! empty( $response['paymentid'] ) ) {
			$order->update_meta_data( '_novabanka_payment_id', sanitize_text_field( $response['paymentid'] ) );
			$order->save();
		}

		return array(
			'result'   => 'success',
			'redirect' => $response['browserRedirectionURL'],
		);
	}
}
