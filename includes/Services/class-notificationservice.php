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
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\MessageHandler;
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
	 * Data Handler instance.
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Constructor.
	 *
	 * @param Logger         $logger          Logger instance.
	 * @param MessageHandler $message_handler Message Handler instance.
	 * @param DataHandler    $data_handler    Data Handler instance.
	 */
	public function __construct(
		Logger $logger,
		MessageHandler $message_handler,
		DataHandler $data_handler
	) {
		$this->logger          = $logger;
		$this->message_handler = $message_handler;
		$this->data_handler    = $data_handler;
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
				array(
					'notification' => $this->data_handler->redact_sensitive_data( $notification_data ),
				)
			);

			// Validate notification data.
			$this->validate_notification( $notification_data );

			// Get order.
			$order = $this->get_order( $notification_data );

			// Process notification.
			$result = $this->process_notification( $order, $notification_data );

			// Log success.
			$this->logger->info(
				'Payment notification processed successfully.',
				array(
					'order_id' => $order->get_id(),
					'result'   => $result,
				)
			);

			return $result;

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Payment notification processing failed.',
				array(
					'error' => $e->getMessage(),
					'data'  => $this->data_handler->redact_sensitive_data( $notification_data ),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Validate notification data.
	 *
	 * @param array $notification_data Notification data to validate.
	 * @throws NovaBankaIPGException When validation fails.
	 */
	private function validate_notification( array $notification_data ): void {
		// Verify message signature.
		if ( ! $this->message_handler->verify_notification_signature( $notification_data ) ) {
			throw new NovaBankaIPGException(
				esc_html__( 'Invalid notification signature.', 'novabanka-ipg-gateway' ),
				'INVALID_SIGNATURE'
			);
		}

		// Verify required fields.
		$required_fields = array( 'paymentid', 'trackid', 'result' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $notification_data[ $field ] ) ) {
				throw new NovaBankaIPGException(
					sprintf(
						/* translators: %s: field name */
						esc_html__( 'Missing required field: %s', 'novabanka-ipg-gateway' ),
						esc_html( $field )
					),
					'MISSING_FIELD'
				);
			}
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
			throw new NovaBankaIPGException(
				sprintf(
					/* translators: %d: order ID */
					esc_html__( 'Order not found: %d', 'novabanka-ipg-gateway' ),
					esc_html( $order_id )
				),
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

		if ( 'CAPTURED' === $result || 'APPROVED' === $result ) {
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
	 * @param WC_Order $order            Order to process.
	 * @param array    $notification_data Notification data.
	 */
	private function process_successful_payment( WC_Order $order, array $notification_data ): void {
		// Update payment details.
		$order->payment_complete( $notification_data['paymentid'] );
		$order->add_order_note(
			sprintf(
				/* translators: 1: Result 2: Reference number */
				esc_html__( 'Payment successful. Result: %1$s, Reference: %2$s', 'novabanka-ipg-gateway' ),
				$notification_data['result'],
				$notification_data['ref']
			)
		);
	}

	/**
	 * Process failed payment notification.
	 *
	 * @param WC_Order $order            Order to process.
	 * @param array    $notification_data Notification data.
	 */
	private function process_failed_payment( WC_Order $order, array $notification_data ): void {
		$order->update_status(
			'failed',
			sprintf(
				/* translators: 1: Result 2: Response code 3: Reference number */
				esc_html__( 'Payment failed. Result: %1$s, Response code: %2$s, Reference: %3$s', 'novabanka-ipg-gateway' ),
				$notification_data['result'],
				$notification_data['responsecode'],
				$notification_data['ref']
			)
		);
	}
}
