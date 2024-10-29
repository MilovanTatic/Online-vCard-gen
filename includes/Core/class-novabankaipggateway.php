<?php
/**
 * NovaBanka IPG Gateway Class
 *
 * @package NovaBankaIPG
 * @subpackage Core
 */

namespace NovaBankaIPG\Core;

use WC_Payment_Gateway;
use WC_Order;
use NovaBankaIPG\Services\PaymentService;
use NovaBankaIPG\Services\NotificationService;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;

/**
 * Main gateway class for NovaBanka IPG integration.
 */
class NovaBankaIPGGateway extends WC_Payment_Gateway {
	/**
	 * Payment Service instance.
	 *
	 * @var PaymentService
	 */
	private $payment_service;

	/**
	 * Notification Service instance.
	 *
	 * @var NotificationService
	 */
	private $notification_service;

	/**
	 * Constructor.
	 *
	 * @param PaymentService      $payment_service      Payment Service instance.
	 * @param NotificationService $notification_service Notification Service instance.
	 */
	public function __construct(
		PaymentService $payment_service,
		NotificationService $notification_service
	) {
		$this->id                 = 'novabankaipg';
		$this->method_title       = __( 'NovaBanka IPG', 'novabanka-ipg-gateway' );
		$this->method_description = __( 'NovaBanka IPG payment gateway integration', 'novabanka-ipg-gateway' );

		// Initialize basic gateway settings.
		$this->init_form_fields();
		$this->init_settings();

		$this->payment_service      = $payment_service;
		$this->notification_service = $notification_service;

		// Set basic gateway properties.
		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		// Add actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_novabankaipg', array( $this, 'process_notification' ) );
	}

	/**
	 * Process payment.
	 *
	 * @param int $order_id Order ID.
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		try {
			return $this->payment_service->process_payment( $order_id, $this->get_payment_settings() );
		} catch ( \Exception $e ) {
			wc_add_notice( esc_html( $e->getMessage() ), 'error' );
			return;
		}
	}

	/**
	 * Process IPG notification.
	 *
	 * @throws NovaBankaIPGException When JSON payload is invalid.
	 */
	public function process_notification(): void {
		try {
			$raw_post          = file_get_contents( 'php://input' );
			$notification_data = json_decode( $raw_post, true );

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new NovaBankaIPGException( 'Invalid JSON payload' );
			}

			// Delegate all notification processing to NotificationService.
			$this->notification_service->handle_notification( $notification_data );

		} catch ( \Exception $e ) {
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				),
				400
			);
		}
	}

	/**
	 * Get payment gateway settings.
	 *
	 * @return array
	 */
	private function get_payment_settings(): array {
		return array(
			'action'            => $this->get_option( 'action' ),
			'terminal_id'       => $this->get_option( 'terminal_id' ),
			'terminal_password' => $this->get_option( 'terminal_password' ),
			'payment_timeout'   => $this->get_option( 'payment_timeout' ),
			'payment_mode'      => $this->get_option( 'payment_mode' ),
		);
	}
}
