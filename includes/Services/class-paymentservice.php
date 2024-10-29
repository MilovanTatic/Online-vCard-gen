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
	 * @param int $order_id Order ID.
	 * @return array Payment processing result.
	 * @throws NovaBankaIPGException When payment processing fails.
	 */
	public function process_payment( int $order_id ): array {
		try {
			$order        = $this->get_order( $order_id );
			$payment_data = $this->prepare_payment_data( $order );

			$response = $this->api_handler->post( '/payment/process', $payment_data );

			if ( ! $this->is_payment_successful( $response['status'] ) ) {
				throw NovaBankaIPGException::payment_failed( $response['message'] ?? '' );
			}

			$order->payment_complete( $response['paymentId'] );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		} catch ( Exception $e ) {
			NovaBankaIPGException::handle_error(
				$e,
				$this->logger,
				'Payment processing',
				array(
					'order_id' => $order_id,
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
		return in_array( $status, array( 'CAPTURED', 'AUTHORIZED' ), true );
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
			throw NovaBankaIPGException::order_not_found();
		}
		return $order;
	}

	/**
	 * Prepare payment data for API request.
	 *
	 * @param WC_Order $order    Order object.
	 * @return array
	 * @throws NovaBankaIPGException When currency is not supported.
	 */
	private function prepare_payment_data( WC_Order $order ): array {
		return array(
			'amount'      => SharedUtilities::format_amount( $order->get_total() ),
			'currency'    => $order->get_currency(),
			'orderId'     => $order->get_id(),
			'description' => sprintf(
				/* translators: %s: Order ID */
				__( 'Payment for order %s', 'novabanka-ipg-gateway' ),
				$order->get_order_number()
			),
		);
	}

	/**
	 * Get return URL.
	 *
	 * @param WC_Order $order    Order object.
	 * @return string
	 */
	private function get_return_url( WC_Order $order ): string {
		return $order->get_checkout_order_received_url();
	}
}
