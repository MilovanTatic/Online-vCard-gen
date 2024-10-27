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
use WC_Order;
use Exception;

/**
 * Class PaymentService
 *
 * Handles payment-related operations including initialization, refunds, and verification with NovaBankaIPG.
 */
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
	 * @throws Exception When payment initialization fails.
	 */
	public function initialize_payment( WC_Order $order ) {
		try {
			// Prepare payment data.
			$payment_data = array(
				'trackid'      => $order->get_id(),
				'amount'       => $order->get_total(),
				'currency'     => $order->get_currency(),
				'response_url' => $order->get_checkout_payment_url( true ),
				'error_url'    => $order->get_checkout_payment_url( false ),
				'language'     => 'EN', // Default language for now.
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
			throw new Exception( 'Payment initialization failed: ' . esc_html( $e->getMessage() ) );
		}
	}
}
