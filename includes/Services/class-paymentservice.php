<?php
/**
 * Payment Service Class
 *
 * This class is responsible for handling all payment-related logic.
 * It handles payment initialization, refunds, and verification with the NovaBanka IPG.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WC_Order;
use Exception;

class PaymentService {
	/**
	 * API Handler instance.
	 *
	 * @var APIHandler
	 */
	private $api_handler;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor for the PaymentService class.
	 *
	 * @param APIHandler $api_handler API handler instance.
	 * @param Logger     $logger Logger instance.
	 */
	public function __construct( APIHandler $api_handler, Logger $logger ) {
		$this->api_handler = $api_handler;
		$this->logger      = $logger;
	}

	/**
	 * Initialize a payment for an order.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @return array Response data from the payment initialization.
	 * @throws NovaBankaIPGException When payment initialization fails.
	 */
	public function initialize_payment( WC_Order $order ) {
		try {
			// Retrieve currency and language from settings if needed.
			$currency = Config::get_setting( 'currency' ) ?? $order->get_currency();
			$language = Config::get_setting( 'language' ) ?? 'EN';

			// Prepare payment data.
			$payment_data = array(
				'trackid'      => $order->get_id(),
				'amount'       => $order->get_total(),
				'currency'     => $currency,
				'response_url' => $order->get_checkout_payment_url( true ),
				'error_url'    => $order->get_checkout_payment_url( false ),
				'language'     => $language,
				'secret_key'   => Config::get_setting( 'secret_key' ), // Include secret key for request security.
			);

			// Call the API handler to initialize the payment.
			$response = $this->api_handler->send_payment_init( $payment_data );

			// Log successful initialization.
			$this->logger->info(
				'Payment initialized successfully.',
				array(
					'order_id' => $order->get_id(),
					'response' => $response,
				)
			);

			return $response;
		} catch ( Exception $e ) {
			// Log the error and throw an exception.
			$this->logger->error(
				'Payment initialization failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( 'Payment initialization failed: ' . $e->getMessage() );
		}
	}

	/**
	 * Refund a payment for an order.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @param float    $amount Amount to refund.
	 * @return array Response data from the refund request.
	 * @throws NovaBankaIPGException When the refund process fails.
	 */
	public function process_refund( WC_Order $order, float $amount ) {
		try {
			// Validate the refund amount.
			if ( $amount > $order->get_total() ) {
				throw new NovaBankaIPGException( 'Refund amount exceeds the original order total.' );
			}

			if ( $amount <= 0 ) {
				throw new NovaBankaIPGException( 'Refund amount must be greater than zero.' );
			}

			// Prepare refund data.
			$refund_data = array(
				'trackid'    => $order->get_id(),
				'amount'     => $amount,
				'currency'   => $order->get_currency(),
				'tranid'     => $order->get_transaction_id(), // Include transaction ID for better traceability.
				'secret_key' => Config::get_setting( 'secret_key' ), // Include secret key for request security.
			);

			// Call the API handler to process the refund.
			$response = $this->api_handler->process_refund( $refund_data );

			// Update WooCommerce order status if the refund is successful.
			$order->add_order_note(
				sprintf(
					__( 'Refund processed successfully. Amount: %1$s %2$s. Transaction ID: %3$s', 'novabanka-ipg-gateway' ),
					$amount,
					$order->get_currency(),
					$response['tranid'] ?? 'N/A'
				)
			);

			// Log successful refund.
			$this->logger->info(
				'Refund processed successfully.',
				array(
					'order_id' => $order->get_id(),
					'response' => $response,
				)
			);

			return $response;
		} catch ( Exception $e ) {
			// Log the error and throw an exception.
			$this->logger->error(
				'Refund process failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( 'Refund process failed: ' . $e->getMessage() );
		}
	}
}
