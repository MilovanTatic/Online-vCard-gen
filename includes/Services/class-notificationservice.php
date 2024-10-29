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
	 * Constructor.
	 *
	 * @param Logger         $logger          Logger instance.
	 * @param MessageHandler $message_handler Message Handler instance.
	 */
	public function __construct(
		Logger $logger,
		MessageHandler $message_handler
	) {
		$this->logger          = $logger;
		$this->message_handler = $message_handler;
	}

	/**
	 * Handle notification from IPG.
	 *
	 * @param array $notification_data Notification data from IPG.
	 * @throws NovaBankaIPGException When notification processing fails.
	 */
	public function handle_notification( array $notification_data ): void {
		if ( ! SharedUtilities::validate_notification_data( $notification_data ) ) {
			throw NovaBankaIPGException::invalid_notification();
		}

		$order = $this->get_order_from_notification( $notification_data );
		$this->update_order_status( $order, $notification_data );
	}

	/**
	 * Get order from notification data.
	 *
	 * @param array $notification_data Notification data.
	 * @return WC_Order Order object.
	 * @throws NovaBankaIPGException When order cannot be found.
	 */
	private function get_order_from_notification( array $notification_data ): WC_Order {
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
	 * Update order status based on notification data.
	 *
	 * @param WC_Order $order            Order object.
	 * @param string   $status           Order status.
	 * @param array    $notification_data Notification data.
	 */
	private function update_order_status( WC_Order $order, string $status, array $notification_data ): void {
		if ( 'completed' === $status ) {
			$order->payment_complete( $notification_data['paymentid'] );
			$order->add_order_note(
				sprintf(
					/* translators: %s: payment ID */
					esc_html__( 'Payment completed successfully. Payment ID: %s', 'novabanka-ipg-gateway' ),
					esc_html( $notification_data['paymentid'] )
				)
			);
		} else {
			$order->update_status( $status );
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

	/**
	 * Process IPG notification.
	 *
	 * @return void
	 * @throws NovaBankaIPGException When notification processing fails.
	 */
	public function process_ipg_notification(): void {
		try {
			$notification_data = $this->message_handler->get_json_payload();
			$this->handle_notification( $notification_data );
			wp_send_json_success();
		} catch ( Exception $e ) {
			NovaBankaIPGException::handle_error( $e, $this->logger, 'Notification processing' );
		}
	}
}
