<?php
/**
 * NovaBanka IPG Gateway Class
 *
 * This class integrates the NovaBanka IPG into WooCommerce.
 * Handles payment settings, order processing, and general WooCommerce compatibility.
 *
 * @package NovaBankaIPG\Core
 * @since 1.0.1
 */

namespace NovaBankaIPG\Core;

use NovaBankaIPG\Services\PaymentService;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use WC_Payment_Gateway;
use Exception;

/**
 * NovaBankaIPGGateway Class
 *
 * Extends WC_Payment_Gateway to integrate NovaBanka IPG into WooCommerce.
 * Handles payment processing, gateway settings, and order management.
 */
class NovaBankaIPGGateway extends WC_Payment_Gateway {
	/**
	 * API Handler instance.
	 *
	 * @var APIHandler
	 */
	protected $api_handler;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Payment Service instance.
	 *
	 * @var PaymentService
	 */
	protected $payment_service;

	/**
	 * Constructor for the gateway.
	 *
	 * @param APIHandler|null $api_handler The API handler instance.
	 * @param Logger|null     $logger The logger instance.
	 */
	public function __construct( APIHandler $api_handler = null, Logger $logger = null ) {
		$this->id                 = 'novabankaipg';
		$this->has_fields         = true;
		$this->method_title       = __( 'NovaBanka IPG', 'novabanka-ipg-gateway' );
		$this->method_description = __( 'Accept payments through NovaBanka IPG gateway with 3D Secure.', 'novabanka-ipg-gateway' );

		// Initialize dependencies.
		$this->api_handler = $api_handler ?? new APIHandler();
		$this->logger      = $logger ?? new Logger( 'novabankaipg', false );

		// Initialize PaymentService.
		$this->payment_service = new PaymentService( $this->api_handler, $this->logger );

		// Load settings.
		$this->init_form_fields();
		$this->init_settings();

		// Add hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
	}

	/**
	 * Process the payment for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|
	 * @throws Exception When payment processing fails.
	 */
	public function process_payment( $order_id ) {
		try {
			$order = wc_get_order( $order_id );

			// Use PaymentService to initialize the payment.
			$response = $this->payment_service->initialize_payment( $order );

			// Store payment ID and redirect user to the payment gateway.
			$order->update_status( 'on-hold', __( 'Awaiting payment gateway response.', 'novabanka-ipg-gateway' ) );
			return array(
				'result'   => 'success',
				'redirect' => $response['browserRedirectionURL'],
			);
		} catch ( Exception $e ) {
			$this->logger->error(
				'Payment process failed.',
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);
			wc_add_notice( __( 'Payment error: ', 'novabanka-ipg-gateway' ) . $e->getMessage(), 'error' );
			return array(
				'result' => 'failure',
			);
		}
	}

	/**
	 * Receipt page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function receipt_page( $order_id ) {
		echo '<p>' . esc_html__( 'Thank you for your order, please click the button below to pay.', 'novabanka-ipg-gateway' ) . '</p>';
		echo '<button id="novabanka-ipg-pay-button">' . esc_html__( 'Proceed to Payment', 'novabanka-ipg-gateway' ) . '</button>';
	}
}
