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
use NovaBankaIPG\Services\NotificationService;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\Config;
use WC_Payment_Gateway;
use Exception;

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
	 * Notification Service instance.
	 *
	 * @var NotificationService
	 */
	protected $notification_service;

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
		$this->logger      = $logger ?? new Logger( 'novabankaipg' );

		// Initialize PaymentService and NotificationService.
		$this->payment_service      = new PaymentService( $this->api_handler, $this->logger );
		$this->notification_service = new NotificationService( $this->api_handler, $this->logger );

		// Load settings using Config utility.
		$this->init_form_fields();
		$this->init_settings();
	}

	/**
	 * Initialize gateway settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'novabanka-ipg-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable NovaBanka IPG Payment Gateway', 'novabanka-ipg-gateway' ),
				'default' => Config::get_setting( 'enabled' ) ?? 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => __( 'The title the user sees during checkout.', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'title' ) ?? __( 'NovaBanka IPG', 'novabanka-ipg-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'novabanka-ipg-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'The description the user sees during checkout.', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'description' ) ?? __( 'Pay securely using NovaBanka IPG.', 'novabanka-ipg-gateway' ),
			),
		);
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
			$this->logger->info(
				'Payment process initialized.',
				array(
					'order_id' => $order_id,
					'response' => $response,
				)
			);
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

	/**
	 * Handle notification callback from IPG.
	 *
	 * This method is called when the IPG sends a notification regarding payment status.
	 */
	public function handle_notification_callback() {
		try {
			$notification_data = $_POST; // Assuming IPG sends POST data.

			// Use NotificationService to handle the notification.
			$this->notification_service->handle_notification( $notification_data );

			// Respond to IPG to confirm successful processing.
			http_response_code( 200 );
			$this->logger->info( 'Notification callback handled successfully.', array( 'notification_data' => $notification_data ) );
			echo 'OK';
		} catch ( Exception $e ) {
			$this->logger->error(
				'Notification callback handling failed.',
				array(
					'error' => $e->getMessage(),
				)
			);
			http_response_code( 500 );
			echo 'FAIL';
		}
	}
}
