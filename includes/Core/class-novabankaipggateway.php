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
	}

	/**
	 * Initialize dependencies.
	 */
	private function init_dependencies(): void {
		// Initialize logger first.
		$this->logger       = new Logger();
		$this->data_handler = new DataHandler();

		// Get settings.
		$settings = Config::get_all_settings();

		// Initialize API handler.
		$this->api_handler = new APIHandler(
			$settings['api_endpoint'] ?? '',
			$settings['terminal_id'] ?? '',
			$settings['terminal_password'] ?? '',
			$settings['secret_key'] ?? '',
			$this->logger,
			$this->data_handler,
			$settings['test_mode'] ?? 'yes'
		);

		// Initialize 3DS handler.
		$this->threeds_handler = new ThreeDSHandler(
			$this->api_handler,
			$this->logger
		);

		// Initialize payment service.
		$this->payment_service = new PaymentService(
			$this->api_handler,
			$this->logger,
			$this->data_handler
		);
	}

	/**
	 * Initialize gateway settings form fields.
	 */
	public function init_form_fields(): void {
		$this->form_fields = Config::get_form_fields();
	}

	/**
	 * Process the payment for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array Payment result data.
	 */
	public function process_payment( $order_id ): array {
		try {
			$order = wc_get_order( $order_id );

			$this->logger->info( 'Payment process initialized.', array( 'order_id' => $order_id ) );

			// Check if the gateway is in test mode and log accordingly.
			if ( Config::is_test_mode() ) {
				$this->logger->info( 'Processing payment in test mode.', array( 'order_id' => $order_id ) );
			}

			// Prepare payment data
			$payment_data = array(
				'order_id' => $order_id,
				'amount'   => $order->get_total(),
				'currency' => $order->get_currency(),
				'trackid'  => $order->get_order_key(),
			);

			// Use PaymentService to initialize the payment.
			$response = $this->payment_service->initialize_payment( $order, $payment_data );

			// Store payment ID and redirect user to the payment gateway.
			$order->update_status(
				'on-hold',
				esc_html__( 'Awaiting payment gateway response.', 'novabanka-ipg-gateway' )
			);

			return array(
				'result'   => 'success',
				'redirect' => $response['browserRedirectionURL'],
			);

		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'Payment process failed.',
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);

			wc_add_notice(
				esc_html__( 'Payment error: ', 'novabanka-ipg-gateway' ) . esc_html( $e->getMessage() ),
				'error'
			);

			return array(
				'result' => 'failure',
			);
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
}
