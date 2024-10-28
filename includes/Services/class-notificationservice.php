<?php
/**
 * NotificationService Class
 *
 * This class is responsible for managing notification-related logic for NovaBanka IPG.
 * It handles notifications received from IPG and processes them accordingly.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use WC_Order;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use Exception;

/**
 * Class NotificationService
 *
 * Handles IPG payment notifications and processes order status updates.
 */
class NotificationService {
	/**
	 * API Handler instance.
	 *
	 * @var APIHandler
	 */
	private $api_handler;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Data handler instance.
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Constructor.
	 *
	 * @param APIHandler  $api_handler  API handler instance.
	 * @param Logger      $logger       Logger instance.
	 * @param DataHandler $data_handler Data handler instance.
	 */
	public function __construct(
		APIHandler $api_handler,
		Logger $logger,
		DataHandler $data_handler
	) {
		$this->api_handler  = $api_handler;
		$this->logger       = $logger;
		$this->data_handler = $data_handler;
	}

	/**
	 * Handle incoming notification from IPG.
	 *
	 * @param array $notification_data The notification data received from IPG.
	 * @return void
	 * @throws NovaBankaIPGException When the notification handling fails.
	 */
	public function handle_notification( array $notification_data ): void {
		try {
			// Allow plugins to modify notification data.
			$notification_data = apply_filters( 'novabankaipg_before_notification_process', $notification_data );

			// Verify message signature.
			$this->verify_notification_signature( $notification_data );

			// Log the notification if in debug mode.
			if ( Config::is_debug_mode() ) {
				$this->logger->debug( 'Notification received', array( 'notification_data' => $notification_data ) );
			}

			$order_id = $notification_data['paymentid'];
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				throw new NovaBankaIPGException( 'Order not found for ID: ' . esc_html( $order_id ) );
			}

			// Handle different notification statuses.
			switch ( $notification_data['status'] ) {
				case 'SUCCESS':
					$this->process_successful_payment( $order, $notification_data );
					$this->logger->info( 'Payment notification processed successfully.', array( 'payment_id' => $notification_data['paymentid'] ) );
					break;
				case 'FAILED':
					$this->process_failed_payment( $order, $notification_data );
					$this->logger->error( 'Payment notification indicates a failure.', array( 'payment_id' => $notification_data['paymentid'] ) );
					break;
				case 'DECLINED':
					$this->process_declined_payment( $order, $notification_data );
					$this->logger->warning( 'Payment was declined.', array( 'payment_id' => $notification_data['paymentid'] ) );
					break;
				case 'CANCELLED':
					$this->process_cancelled_payment( $order, $notification_data );
					$this->logger->info( 'Payment was cancelled by the user.', array( 'payment_id' => $notification_data['paymentid'] ) );
					break;
				default:
					$this->logger->warning(
						'Unhandled payment status received in notification.',
						array(
							'payment_id' => $notification_data['paymentid'],
							'status'     => $notification_data['status'],
						)
					);
					throw new NovaBankaIPGException( 'Unhandled payment status received in notification.' );
			}

			/**
			 * Action after notification processing.
			 *
			 * @since 1.0.1
			 * @param WC_Order $order            The order being processed.
			 * @param array    $notification_data The notification data.
			 */
			do_action( 'novabankaipg_after_notification_process', $order, $notification_data );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Notification handling failed.',
				array(
					'error'             => esc_html( $e->getMessage() ),
					'notification_data' => $notification_data,
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Process successful payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function process_successful_payment( WC_Order $order, array $notification_data ): void {
		$formatted_amount = SharedUtilities::format_amount( $order->get_total() );
		$order->payment_complete( $notification_data['tranid'] );
		$order->add_order_note(
			sprintf(
				/* translators: %1$s: Transaction ID, %2$s: Auth Code, %3$s: Amount */
				esc_html__( 'Payment completed successfully. Transaction ID: %1$s, Auth Code: %2$s, Amount: %3$s', 'novabanka-ipg-gateway' ),
				$notification_data['tranid'],
				$notification_data['auth'],
				$formatted_amount
			)
		);

		$this->store_transaction_data( $order, $notification_data );

		/**
		 * Action after successful payment processing.
		 *
		 * @since 1.0.1
		 * @param WC_Order $order            The order being processed.
		 * @param array    $notification_data The notification data.
		 */
		do_action( 'novabankaipg_after_successful_payment', $order, $notification_data );
	}

	/**
	 * Process declined payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function process_declined_payment( WC_Order $order, array $notification_data ): void {
		$formatted_amount = SharedUtilities::format_amount( $order->get_total() );
		$order->update_status(
			'on-hold',
			sprintf(
				/* translators: %1$s: Result, %2$s: Code, %3$s: Amount */
				esc_html__( 'Payment was declined. Result: %1$s, Code: %2$s, Amount: %3$s', 'novabanka-ipg-gateway' ),
				$notification_data['result'],
				$notification_data['responsecode'] ?? 'N/A',
				$formatted_amount
			)
		);

		/**
		 * Action after declined payment processing.
		 *
		 * @since 1.0.1
		 * @param WC_Order $order            The order being processed.
		 * @param array    $notification_data The notification data.
		 */
		do_action( 'novabankaipg_after_declined_payment', $order, $notification_data );
	}

	/**
	 * Process failed payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function process_failed_payment( WC_Order $order, array $notification_data ): void {
		$formatted_amount = SharedUtilities::format_amount( $order->get_total() );
		$order->update_status(
			'failed',
			sprintf(
				/* translators: %1$s: Result, %2$s: Code, %3$s: Amount */
				esc_html__( 'Payment failed. Result: %1$s, Code: %2$s, Amount: %3$s', 'novabanka-ipg-gateway' ),
				$notification_data['result'],
				$notification_data['responsecode'] ?? 'N/A',
				$formatted_amount
			)
		);

		/**
		 * Action after failed payment processing.
		 *
		 * @since 1.0.1
		 * @param WC_Order $order            The order being processed.
		 * @param array    $notification_data The notification data.
		 */
		do_action( 'novabankaipg_after_failed_payment', $order, $notification_data );
	}

	/**
	 * Process cancelled payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function process_cancelled_payment( WC_Order $order, array $notification_data ): void {
		$formatted_amount = SharedUtilities::format_amount( $order->get_total() );
		$order->update_status(
			'cancelled',
			sprintf(
				/* translators: %1$s: Result, %2$s: Amount */
				esc_html__( 'Payment was cancelled. Result: %1$s, Amount: %2$s', 'novabanka-ipg-gateway' ),
				$notification_data['result'],
				$formatted_amount
			)
		);

		/**
		 * Action after cancelled payment processing.
		 *
		 * @since 1.0.1
		 * @param WC_Order $order            The order being processed.
		 * @param array    $notification_data The notification data.
		 */
		do_action( 'novabankaipg_after_cancelled_payment', $order, $notification_data );
	}

	/**
	 * Store transaction data in order meta.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function store_transaction_data( WC_Order $order, array $notification_data ): void {
		$order->update_meta_data( '_novabankaipg_auth_code', $notification_data['auth'] );
		$order->update_meta_data( '_novabankaipg_card_type', $notification_data['cardtype'] ?? 'unknown' );
		$order->update_meta_data( '_novabankaipg_card_last4', $notification_data['cardLastFourDigits'] );
		$order->update_meta_data( '_novabankaipg_payment_reference', $notification_data['paymentReference'] ?? 'N/A' );
		$order->save();
	}

	/**
	 * Verify notification signature.
	 *
	 * @param array $notification_data Notification data to verify.
	 * @return void
	 * @throws NovaBankaIPGException If signature verification fails.
	 */
	private function verify_notification_signature( array $notification_data ): void {
		$verifier_fields = array(
			$notification_data['msgName'],
			$notification_data['version'],
			$notification_data['paymentid'],
			$notification_data['amt'],
			$notification_data['status'],
			$notification_data['result'],
		);

		$calculated_verifier = SharedUtilities::generate_message_verifier( ...$verifier_fields );

		if ( ! hash_equals( $calculated_verifier, $notification_data['msgVerifier'] ) ) {
			throw new NovaBankaIPGException( 'Invalid notification message signature.' );
		}
	}
}
