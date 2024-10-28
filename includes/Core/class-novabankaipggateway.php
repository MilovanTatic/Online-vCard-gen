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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WC_Payment_Gateway;
use WC_Order;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\ThreeDSHandler;
use NovaBankaIPG\Services\PaymentService;
use NovaBankaIPG\Services\NotificationService;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;

/**
 * Main gateway class for NovaBanka IPG integration.
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
	 * Data Handler instance.
	 *
	 * @var DataHandler
	 */
	protected $data_handler;

	/**
	 * ThreeDS Handler instance.
	 *
	 * @var ThreeDSHandler
	 */
	protected $threeds_handler;

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
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'novabankaipg';
		$this->method_title       = __( 'NovaBanka IPG', 'novabanka-ipg-gateway' );
		$this->method_description = __( 'NovaBanka IPG payment gateway integration', 'novabanka-ipg-gateway' );

		// Initialize basic gateway settings.
		$this->init_form_fields();
		$this->init_settings();

		// Initialize dependencies.
		$this->init_dependencies();

		// Set basic gateway properties.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		// Add actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Register notification endpoint.
		$this->register_notification_endpoint();
	}

	/**
	 * Initialize dependencies.
	 */
	private function init_dependencies(): void {
		// Initialize logger first.
		$this->logger = new Logger();

		// Initialize data handler.
		$this->data_handler = new DataHandler();

		// Get settings.
		$settings = Config::get_all_settings();

		// Initialize API handler.
		$this->api_handler = new APIHandler(
			$settings['api_endpoint'],
			$settings['terminal_id'],
			$settings['terminal_password'],
			$settings['secret_key'],
			$this->logger,
			$this->data_handler,
			$settings['test_mode'] ?? 'yes'
		);

		// Initialize payment service.
		$this->payment_service = new PaymentService(
			$this->api_handler,
			$this->logger,
			$this->data_handler
		);

		// Initialize notification service with correct dependencies.
		$this->notification_service = new NotificationService(
			$this->logger,      // Pass Logger instance first
			$this->data_handler // Pass DataHandler instance second
		);

		// Initialize 3DS handler.
		$this->threeds_handler = new ThreeDSHandler(
			$this->api_handler,
			$this->logger
		);
	}

	/**
	 * Initialize gateway settings form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = Config::get_form_fields();
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 * @throws NovaBankaIPGException When payment processing fails.
	 */
	public function process_payment( int $order_id ): array {
		try {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				throw new NovaBankaIPGException( esc_html__( 'Invalid order ID.', 'novabanka-ipg-gateway' ) );
			}

			// Prepare payment data.
			$payment_data = array(
				'order_id' => $order_id,
				'amount'   => $order->get_total(),
				'currency' => $order->get_currency(),
				'trackid'  => $order->get_order_key(),
				'langid'   => substr( get_locale(), 0, 2 ),
				'email'    => $order->get_billing_email(),
			);

			// Allow plugins to modify payment data.
			$payment_data = apply_filters( 'novabankaipg_payment_data', $payment_data, $order );

			// Initialize payment through PaymentService.
			$response = $this->payment_service->initialize_payment( $order, $payment_data );

			// Update order status.
			$order->update_status(
				'on-hold',
				esc_html__( 'Awaiting payment confirmation from NovaBanka IPG.', 'novabanka-ipg-gateway' )
			);

			return array(
				'result'   => 'success',
				'redirect' => $response['browserRedirectionURL'],
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Payment processing failed.',
				array(
					'order_id' => $order_id,
					'error'    => esc_html( $e->getMessage() ),
				)
			);

			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Safe logging method.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Context data.
	 * @param string $level   Log level (default: 'info').
	 */
	protected function log( string $message, array $context = array(), string $level = 'info' ): void {
		if ( $this->logger ) {
			$this->logger->{$level}( $message, $context );
		}
	}

	/**
	 * Handle IPG notification.
	 *
	 * @param array $notification_data Notification data from IPG.
	 * @return void
	 */
	public function handle_notification( array $notification_data ): void {
		try {
			// Let NotificationService handle the notification.
			$this->notification_service->handle_notification( $notification_data );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Notification handling failed.',
				array(
					'error' => $e->getMessage(),
					'data'  => $this->data_handler->redact_sensitive_data( $notification_data ),
				)
			);

			wp_send_json_error(
				array(
					'message' => esc_html__( 'Notification processing failed.', 'novabanka-ipg-gateway' ),
				)
			);
		}
	}

	/**
	 * Register notification endpoint.
	 */
	private function register_notification_endpoint(): void {
		add_action( 'woocommerce_api_novabankaipg', array( $this, 'process_notification' ) );
	}

	/**
	 * Process IPG notification.
	 */
	public function process_notification(): void {
		$raw_post          = file_get_contents( 'php://input' );
		$notification_data = json_decode( $raw_post, true );

		if ( empty( $notification_data ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid notification data.', 'novabanka-ipg-gateway' ),
				)
			);
			return;
		}

		$this->handle_notification( $notification_data );
	}
}
