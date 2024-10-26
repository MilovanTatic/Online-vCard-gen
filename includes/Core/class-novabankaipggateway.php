<?php
/**
 * Core Gateway Implementation
 *
 * @package     NovaBankaIPG\Core
 * @since       1.0.0
 */

namespace NovaBankaIPG\Core;

use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\ThreeDSHandler;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WC_Payment_Gateway;
use WC_Log_Handler_File;

defined( 'ABSPATH' ) || exit;

/**
 * Gateway Class
 *
 * @since 1.0.0
 * @extends WC_Payment_Gateway
 */
class NovaBankaIPGGateway extends WC_Payment_Gateway {
	/**
	 * API Handler instance
	 *
	 * @var APIHandler
	 */
	protected $api_handler;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	protected $logger;

	/**
	 * Data Handler instance
	 *
	 * @var DataHandler
	 */
	protected $data_handler;

	/**
	 * 3DS Handler instance
	 *
	 * @var ThreeDSHandler
	 */
	protected $three_ds_handler;

	/**
	 * Test mode flag
	 *
	 * @var bool
	 */
	protected $test_mode;

	/**
	 * Debug mode flag
	 *
	 * @var bool
	 */
	protected $debug;

	/**
	 * Constructor for the gateway.
	 *
	 * @param APIHandler|null     $api_handler The API handler instance.
	 * @param ThreeDSHandler|null $three_ds_handler The 3DS handler instance.
	 * @param DataHandler|null    $data_handler The data handler instance.
	 * @param Logger|null         $logger The logger instance.
	 */
	public function __construct(
		APIHandler $api_handler = null,
		ThreeDSHandler $three_ds_handler = null,
		DataHandler $data_handler = null,
		Logger $logger = null
	) {
		// Setup general properties.
		$this->id                 = 'novabankaipg';
		$this->icon               = apply_filters( 'wc_novabankaipg_icon', '' );
		$this->has_fields         = true;
		$this->method_title       = __( 'NovaBanka IPG', 'novabanka-ipg-gateway' );
		$this->method_description = __( 'Accept payments through NovaBanka IPG gateway with 3D Secure.', 'novabanka-ipg-gateway' );

		// Define support for various features.
		$this->supports = array(
			'products',
			'refunds',
			'tokenization',
			'add_payment_method',
			'default_credit_card_form',
		);

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->test_mode   = 'yes' === $this->get_option( 'test_mode' );
		$this->debug       = 'yes' === $this->get_option( 'debug' );

		// Set dependencies if provided.
		if ( $api_handler && $three_ds_handler && $data_handler && $logger ) {
			$this->api_handler      = $api_handler;
			$this->three_ds_handler = $three_ds_handler;
			$this->data_handler     = $data_handler;
			$this->logger           = $logger;
		} else {
			$this->init_components();
		}

		// Initialize hooks.
		$this->init_hooks();

		// Add API endpoint handler with early logging
		add_action(
			'woocommerce_api_novabankaipg',
			function () {
				// Log raw request as early as possible
				$this->logger->debug(
					'Raw IPG notification received',
					array(
						'method'    => $_SERVER['REQUEST_METHOD'],
						'raw_input' => file_get_contents( 'php://input' ),
						'post'      => $_POST,
						'get'       => $_GET,
						'headers'   => getallheaders(),
					)
				);

				// Then call the normal handler
				$this->handle_ipg_notification();
			}
		);

		// Register the notification endpoint
		add_action( 'woocommerce_api_novabankaipg_notification', array( $this, 'handle_ipg_notification' ) );
	}

	/**
	 * Initialize gateway components
	 *
	 * @return void
	 */
	protected function init_components() {
		// Initialize Logger first for debugging.
		$this->logger = new Logger( 'novabankaipg', $this->debug );

		// Initialize Data Handler.
		$this->data_handler = new DataHandler();

		// Get API configuration.
		$api_endpoint = $this->test_mode
			? 'https://ipgtest.novabanka.com/IPGWeb/servlet/'
			: 'https://ipg.novabanka.com/IPGWeb/servlet/';

		// Initialize API Handler with settings from WC_Payment_Gateway.
		$this->api_handler = new APIHandler(
			$api_endpoint,
			$this->get_option( 'terminal_id' ),
			$this->get_option( 'terminal_password' ),
			$this->get_option( 'secret_key' ),
			$this->logger,
			$this->data_handler,
			$this->test_mode
		);

		// Initialize 3DS Handler last as it depends on API Handler.
		$this->three_ds_handler = new ThreeDSHandler(
			$this->api_handler,
			$this->logger,
			$this->data_handler
		);

		$this->logger->debug(
			'Gateway components initialized',
			array(
				'test_mode' => $this->test_mode,
				'debug'     => $this->debug,
			)
		);
	}

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	protected function init_hooks() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_ipg_notification' ) ); // Notification endpoint
	}

	/**
	 * Initialize form fields for admin settings.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'           => array(
				'title'       => __( 'Enable/Disable', 'novabanka-ipg-gateway' ),
				'label'       => __( 'Enable NovaBanka IPG', 'novabanka-ipg-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'             => array(
				'title'       => __( 'Title', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => __( 'Payment method title that customers see during checkout.', 'novabanka-ipg-gateway' ),
				'default'     => __( 'Credit Card (3D Secure)', 'novabanka-ipg-gateway' ),
				'desc_tip'    => true,
			),
			'description'       => array(
				'title'       => __( 'Description', 'novabanka-ipg-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that customers see during checkout.', 'novabanka-ipg-gateway' ),
				'default'     => __( 'Pay securely using your credit card.', 'novabanka-ipg-gateway' ),
				'desc_tip'    => true,
			),
			'test_mode'         => array(
				'title'       => __( 'Test mode', 'novabanka-ipg-gateway' ),
				'label'       => __( 'Enable Test Mode', 'novabanka-ipg-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'novabanka-ipg-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'debug'             => array(
				'title'       => __( 'Debug log', 'novabanka-ipg-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'novabanka-ipg-gateway' ),
				'default'     => 'no',
				'description' => sprintf(
					/* translators: %s: log path */
					__( 'Log gateway events inside %s', 'novabanka-ipg-gateway' ),
					'<code>' . \WC_Log_Handler_File::get_log_file_path( 'novabankaipg' ) . '</code>'
				),
			),
			'api_credentials'   => array(
				'title'       => __( 'API Credentials', 'novabanka-ipg-gateway' ),
				'type'        => 'title',
				'description' => '',
			),
			'terminal_id'       => array(
				'title'       => __( 'Terminal ID', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your Terminal ID (TranPortalID).', 'novabanka-ipg-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'terminal_password' => array(
				'title'       => __( 'Terminal Password', 'novabanka-ipg-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your Terminal Password.', 'novabanka-ipg-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'secret_key'        => array(
				'title'       => __( 'Secret Key', 'novabanka-ipg-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your Secret Key for message verification.', 'novabanka-ipg-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'advanced_settings' => array(
				'title'       => __( 'Advanced Settings', 'novabanka-ipg-gateway' ),
				'type'        => 'title',
				'description' => '',
			),
			'transaction_type'  => array(
				'title'       => __( 'Transaction Type', 'novabanka-ipg-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select how you would like to capture payments.', 'novabanka-ipg-gateway' ),
				'default'     => 'purchase',
				'desc_tip'    => true,
				'options'     => array(
					'purchase'  => __( 'Purchase (Authorize & Capture)', 'novabanka-ipg-gateway' ),
					'authorize' => __( 'Authorize Only', 'novabanka-ipg-gateway' ),
				),
			),
		);
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 * @throws NovaBankaIPGException When payment processing fails.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		try {
			// 1. Get payment URLs
			$urls = $this->get_payment_urls( $order );

			// 2. Initialize payment with IPG
			$payment_data = array_merge(
				$urls,
				array(
					'trackid'  => $order->get_id(),
					'amount'   => $order->get_total(),
					'currency' => $order->get_currency(),
				)
			);

			$response = $this->api_handler->initialize_payment( $payment_data );

			// Store payment ID
			$order->update_meta_data( '_novabankaipg_payment_id', $response['paymentid'] );
			$order->update_status( 'on-hold', __( 'Awaiting HPP payment', 'novabanka-ipg-gateway' ) );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $response['browserRedirectionURL'],
			);
		} catch ( Exception $e ) {
			$this->logger->error( 'Payment initialization failed', array( 'error' => $e->getMessage() ) );
			throw new Exception( $e->getMessage() );
		}
	}

	/**
	 * Handle refund.
	 *
	 * @param  int    $order_id Order ID.
	 * @param  float  $amount   Refund amount.
	 * @param  string $reason   Refund reason.
	 * @throws NovaBankaIPGException When refund processing fails.
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		try {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				throw new NovaBankaIPGException( 'Order not found' );
			}

			$transaction_id = $order->get_transaction_id();

			if ( ! $transaction_id ) {
				throw new NovaBankaIPGException( 'Transaction ID not found' );
			}

			$refund_data = array(
				'order_id'       => $order_id,
				'amount'         => $amount,
				'currency'       => $order->get_currency(),
				'transaction_id' => $transaction_id,
				'reason'         => $reason,
			);

			$response = $this->api_handler->process_refund( $refund_data );

			if ( 'CAPTURED' === $response['result'] ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: amount 2: transaction ID */
						__( 'Refunded %1$s. Transaction ID: %2$s', 'novabanka-ipg-gateway' ),
						$amount,
						$response['tranid']
					)
				);
				return true;
			}

			throw new NovaBankaIPGException( $response['result'] );

		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'Refund failed: ' . $e->getMessage(),
				array(
					'order_id' => $order_id,
					'amount'   => $amount,
					'error'    => $e->getMessage(),
				)
			);
			return new \WP_Error( 'refund_failed', $e->getMessage() );
		}
	}

	/**
	 * Handle gateway response.
	 *
	 * @return void
	 * @throws NovaBankaIPGException When handling the gateway response fails.
	 */
	public function handle_gateway_response() {
		try {
			$raw_data = file_get_contents( 'php://input' );
			$this->logger->debug( 'Gateway response received', array( 'raw_data' => $raw_data ) );

			$notification = json_decode( $raw_data, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				throw new NovaBankaIPGException( 'Invalid JSON response' );
			}

			// Process notification.
			$response = $this->api_handler->handle_notification( $notification );

			// Update order status.
			$order = wc_get_order( $notification['trackid'] );

			if ( ! $order ) {
				throw new NovaBankaIPGException( 'Order not found' );
			}

			if ( 'CAPTURED' === $notification['result'] ) {
				$this->process_successful_payment( $order, $notification );
			} else {
				$this->process_failed_payment( $order, $notification );
			}

			echo wp_json_encode( $response );
			exit;

		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error( 'Error handling gateway response: ' . $e->getMessage() );
			wp_die( esc_html( $e->getMessage() ), esc_html__( 'Payment Error', 'novabanka-ipg-gateway' ), array( 'response' => 500 ) );
		}
	}

	/**
	 * Process successful payment
	 *
	 * @param \WC_Order $order        Order object.
	 * @param array     $notification Payment notification data.
	 * @return void
	 * @throws NovaBankaIPGException When payment ID does not match.
	 */
	protected function process_successful_payment( $order, $notification ) {
		// Verify payment ID
		$stored_payment_id = $order->get_meta( '_novabankaipg_payment_id' );

		if ( $stored_payment_id !== $notification['paymentid'] ) {
			throw new NovaBankaIPGException( 'Payment ID mismatch.' );
		}

		// Verify notification signature before processing
		if ( ! $this->api_handler->verify_signature( $notification, $notification['msgVerifier'] ) ) {
			throw new NovaBankaIPGException( 'Invalid notification signature.' );
		}

		// Store transaction data
		$order->set_transaction_id( $notification['tranid'] );
		$order->update_meta_data( '_novabankaipg_auth_code', $notification['auth'] );
		$order->update_meta_data( '_novabankaipg_card_type', $notification['cardtype'] );
		$order->update_meta_data( '_novabankaipg_card_last4', $notification['cardLastFourDigits'] );

		$order->add_order_note(
			sprintf(
				__( 'Payment completed successfully. Transaction ID: %1$s, Auth Code: %2$s', 'novabanka-ipg-gateway' ),
				$notification['tranid'],
				$notification['auth']
			)
		);

		$order->payment_complete( $notification['tranid'] );
		$order->save();
	}

	/**
	 * Process failed payment.
	 *
	 * @param \WC_Order $order        Order object.
	 * @param array     $notification Payment notification data.
	 */
	private function process_failed_payment( $order, array $notification ): void {
		$order->update_status(
			'failed',
			sprintf(
				/* translators: 1: payment result, 2: response code */
				__( 'Payment failed. Result: %1$s, Code: %2$s', 'novabanka-ipg-gateway' ),
				$notification['result'],
				$notification['responsecode'] ?? 'N/A'
			)
		);
	}

	/**
	 * Get language code for IPG.
	 *
	 * @return string
	 */
	private function get_language_code(): string {
		$locale       = get_locale();
		$language_map = array(
			'it' => 'ITA',
			'en' => 'USA',
			'fr' => 'FRA',
			'de' => 'DEU',
			'es' => 'ESP',
			'sl' => 'SLO',
			'sr' => 'SRB',
			'pt' => 'POR',
			'ru' => 'RUS',
		);

		$lang_code = substr( $locale, 0, 2 );
		return $language_map[ $lang_code ] ?? 'USA';
	}

	/**
	 * Payment fields display on the checkout page.
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
		echo wp_kses(
			'<button type="submit" class="button alt" id="novabankaipg-pay-button">' .
			esc_html__( 'Proceed to Payment', 'novabanka-ipg-gateway' ) .
			'</button>',
			array(
				'button' => array(
					'type'  => array(),
					'class' => array(),
					'id'    => array(),
				),
			)
		);
	}

	/**
	 * Get the SKU of the first product in the order.
	 *
	 * @param WC_Order $order Order object.
	 * @return string Product SKU or empty string if not found.
	 */
	private function get_product_sku( $order ) {
		$items = $order->get_items();

		if ( empty( $items ) ) {
			return '';
		}

		$item = reset( $items );  // Get first item.
		if ( ! $item ) {
			return '';
		}

		$product = $item->get_product();
		if ( ! $product ) {
			return '';
		}

		return $product->get_sku() ?: '';
	}

	/**
	 * Handle IPG notification (Process B)
	 */
	public function handle_ipg_notification() {
		try {
			// Log raw notification data
			$raw_data = file_get_contents( 'php://input' );
			$this->logger->log_api_communication(
				'NOTIFICATION',
				'wc-api/novabankaipg',
				array(
					'raw_data' => $raw_data,
					'method'   => $_SERVER['REQUEST_METHOD'],
					'query'    => $_GET,
					'headers'  => getallheaders(),
				)
			);

			$order = wc_get_order( $notification['trackid'] );
			if ( ! $order ) {
				throw new Exception( 'Order not found' );
			}

			// Store transaction data regardless of result
			$this->store_transaction_data( $order, $notification );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Notification processing failed',
				array(
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString(),
				)
			);
			wp_send_json( array( 'status' => 'ERROR' ), 500 );
		}
	}

	private function get_payment_urls( $order ): array {
		// Construct the API endpoint URL manually to ensure path format
		$site_url = rtrim( get_site_url(), '/' );
		return array(
			'responseURL' => $site_url . '/wc-api/novabankaipg_notification',
			'errorURL'    => $order->get_checkout_payment_url( true ),
		);
	}
}
