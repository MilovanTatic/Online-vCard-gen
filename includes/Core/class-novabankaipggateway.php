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
	 * Constructor.
	 *
	 * @param APIHandler     $api_handler    API handler instance.
	 * @param Logger         $logger         Logger instance.
	 * @param DataHandler    $data_handler   Data handler instance.
	 * @param ThreeDSHandler $threeds_handler ThreeDS handler instance.
	 * @param PaymentService $payment_service Payment service instance.
	 */
	public function __construct(
		APIHandler $api_handler,
		Logger $logger,
		DataHandler $data_handler,
		ThreeDSHandler $threeds_handler,
		PaymentService $payment_service
	) {
		$this->api_handler     = $api_handler;
		$this->logger          = $logger;
		$this->data_handler    = $data_handler;
		$this->threeds_handler = $threeds_handler;
		$this->payment_service = $payment_service;
	}

	/**
	 * Initialize dependencies.
	 */
	private function init_dependencies(): void {
		$this->logger           = new Logger();
			$this->data_handler = new DataHandler();

		$this->api_handler = new APIHandler(
			Config::get_setting( 'api_endpoint' ),
			Config::get_setting( 'terminal_id' ),
			Config::get_setting( 'terminal_password' ),
			Config::get_setting( 'secret_key' ),
			$this->logger,
			$this->data_handler,
			Config::get_setting( 'test_mode', 'yes' )
		);

		$this->threeds_handler = new ThreeDSHandler(
			$this->api_handler,
			$this->logger
		);

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
		$this->form_fields = array(
			'enabled'      => array(
				'title'   => esc_html__( 'Enable/Disable', 'novabanka-ipg-gateway' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable NovaBanka IPG Payment Gateway', 'novabanka-ipg-gateway' ),
				'default' => Config::get_setting( 'enabled', 'no' ),
			),
			'title'        => array(
				'title'       => esc_html__( 'Title', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => esc_html__( 'The title the user sees during checkout.', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'title', esc_html__( 'NovaBanka IPG', 'novabanka-ipg-gateway' ) ),
				'desc_tip'    => true,
			),
			'description'  => array(
				'title'       => esc_html__( 'Description', 'novabanka-ipg-gateway' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'The description the user sees during checkout.', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'description', esc_html__( 'Pay securely using NovaBanka IPG.', 'novabanka-ipg-gateway' ) ),
			),
			'test_mode'    => array(
				'title'       => esc_html__( 'Test Mode', 'novabanka-ipg-gateway' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Enable Test Mode', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'test_mode', 'yes' ),
				'description' => esc_html__( 'Place the payment gateway in test mode to simulate transactions.', 'novabanka-ipg-gateway' ),
			),
			'api_endpoint' => array(
				'title'       => esc_html__( 'API Endpoint', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => esc_html__( 'The API endpoint URL for the payment gateway.', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'api_endpoint', '' ),
				'desc_tip'    => true,
			),
			'terminal_id'  => array(
				'title'       => esc_html__( 'Terminal ID', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => esc_html__( 'Your terminal ID provided by NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'terminal_id', '' ),
				'desc_tip'    => true,
			),
			'secret_key'   => array(
				'title'       => esc_html__( 'Secret Key', 'novabanka-ipg-gateway' ),
				'type'        => 'password',
				'description' => esc_html__( 'Your secret key provided by NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => Config::get_setting( 'secret_key', '' ),
				'desc_tip'    => true,
			),
		);
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

			$this->log( 'Payment process initialized.', array( 'order_id' => $order_id ) );

			// Check if the gateway is in test mode and log accordingly.
			if ( Config::is_test_mode() ) {
				$this->logger->info( 'Processing payment in test mode.', array( 'order_id' => $order_id ) );
			}

			// Use PaymentService to initialize the payment.
			$response = $this->payment_service->initialize_payment( $order );

			// Store payment ID and redirect user to the payment gateway.
			$order->update_status(
				'on-hold',
				esc_html__( 'Awaiting payment gateway response.', 'novabanka-ipg-gateway' )
			);

			$this->logger->info(
				'Payment process initialized.',
				array(
					'order_id' => $order_id,
					'response' => $response,
				)
			);

			/**
			 * Filter payment response before returning.
			 *
			 * @since 1.0.1
			 * @param array    $response Payment response data.
			 * @param WC_Order $order    Order object.
			 */
			$response = apply_filters( 'novabankaipg_payment_response', $response, $order );

			return array(
				'result'   => 'success',
				'redirect' => $response['browserRedirectionURL'],
			);

		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'Payment process failed.',
				array(
					'order_id' => $order_id,
					'error'    => esc_html( $e->getMessage() ),
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
