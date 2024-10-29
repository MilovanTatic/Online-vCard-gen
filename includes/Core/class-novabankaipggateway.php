<?php
/**
 * NovaBanka IPG Gateway Class
 *
 * @package NovaBankaIPG
 * @subpackage Core
 */

namespace NovaBankaIPG\Core;

use WC_Payment_Gateway;
use NovaBankaIPG\Services\PaymentService;
use NovaBankaIPG\Services\NotificationService;

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
		$this->id = 'novabankaipg';
		$this->init_gateway();

		$this->payment_service      = $payment_service;
		$this->notification_service = $notification_service;

		$this->add_hooks();
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
		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
		}
	}

	/**
	 * Process IPG notification.
	 */
	public function process_notification(): void {
		$this->notification_service->process_ipg_notification();
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
