<?php
/**
 * Notification Service Class
 *
 * Handles payment notifications from the IPG gateway.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use WC_Order;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\MessageHandler;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;

/**
 * Handles payment notification processing.
 */
class NotificationService {
	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Message Handler instance.
	 *
	 * @var MessageHandler
	 */
	private $message_handler;

	/**
	 * Payment Service instance.
	 *
	 * @var PaymentService
	 */
	private $payment_service;

	/**
	 * Constructor.
	 *
	 * @param Logger         $logger          Logger instance.
	 * @param MessageHandler $message_handler Message Handler instance.
	 * @param PaymentService $payment_service Payment Service instance.
	 */
	public function __construct(
		Logger $logger,
		MessageHandler $message_handler,
		PaymentService $payment_service
	) {
		$this->logger          = $logger;
		$this->message_handler = $message_handler;
		$this->payment_service = $payment_service;
	}

	/**
	 * Handle notification from IPG.
	 *
	 * @param array $notification_data Notification data from IPG.
	 * @return array Response data.
	 * @throws NovaBankaIPGException When notification processing fails.
	 */
	public function handle_notification( array $notification_data ): array {
		try {
			$this->logger->info(
				'Processing payment notification.',
				array( 'notification' => SharedUtilities::redact_sensitive_data( $notification_data ) )
			);

			SharedUtilities::validate_required_fields( $notification_data, array( 'paymentid', 'trackid', 'result' ) );
			$order  = $this->get_order( $notification_data );
			$result = $this->process_notification( $order, $notification_data );

			$this->logger->info(
				'Payment notification processed successfully.',
				array(
					'order_id' => $order->get_id(),
					'result'   => $result,
				)
			);

			return $result;

		} catch ( \Exception $e ) {
			$this->logger->log_error_and_throw(
				'Payment notification processing failed.',
				array(
					'error' => $e->getMessage(),
					'data'  => SharedUtilities::redact_sensitive_data( $notification_data ),
				)
			);
		}
	}

	/**
	 * Get order from notification data.
	 *
	 * @param array $notification_data Notification data.
	 * @return WC_Order Order object.
	 * @throws NovaBankaIPGException When order cannot be found.
	 */
	private function get_order( array $notification_data ): WC_Order {
		$order_id = absint( $notification_data['trackid'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->logger->log_error_and_throw(
				sprintf(
					/* translators: %d: order ID */
					esc_html__( 'Order not found: %d', 'novabanka-ipg-gateway' ),
					$order_id
				),
				array( 'order_id' => $order_id ),
				'ORDER_NOT_FOUND'
			);
		}

		return $order;
	}

	/**
	 * Process notification for order.
	 *
	 * @param WC_Order $order            Order to process.
	 * @param array    $notification_data Notification data.
	 * @return array Response data.
	 */
	private function process_notification( WC_Order $order, array $notification_data ): array {
		$result = $notification_data['result'];

		if ( $this->payment_service->is_payment_successful( $result ) ) {
			$this->process_successful_payment( $order, $notification_data );
		} else {
			$this->process_failed_payment( $order, $notification_data );
		}

		/**
		 * Fires after payment notification is processed.
		 *
		 * @param WC_Order $order            The order object.
		 * @param array    $notification_data The notification data.
		 */
		do_action( 'novabankaipg_after_notification', $order, $notification_data );

		return array(
			'success'  => true,
			'order_id' => $order->get_id(),
			'result'   => $result,
		);
	}

	/**
	 * Process successful payment notification.
	 *
	 * @param WC_Order $order            Order object.
	 * @param array    $notification_data Notification data.
	 */
	private function process_successful_payment( WC_Order $order, array $notification_data ): void {
		$order->payment_complete( $notification_data['paymentid'] );
		$order->add_order_note(
			sprintf(
				/* translators: %s: payment ID */
				esc_html__( 'Payment completed successfully. Payment ID: %s', 'novabanka-ipg-gateway' ),
				esc_html( $notification_data['paymentid'] )
			)
		);
	}

	/**
	 * Process failed payment notification.
	 *
	 * @param WC_Order $order            Order object.
	 * @param array    $notification_data Notification data.
	 */
	private function process_failed_payment( WC_Order $order, array $notification_data ): void {
		$order->update_status( 'failed' );
		$order->add_order_note(
			sprintf(
				/* translators: 1: result code, 2: payment ID */
				esc_html__( 'Payment failed. Result: %1$s, Payment ID: %2$s', 'novabanka-ipg-gateway' ),
				esc_html( $notification_data['result'] ),
				esc_html( $notification_data['paymentid'] )
			)
		);
	}
}
