<?php
/**
 * NotificationService Class
 *
 * This class is responsible for handling all payment notification-related logic.
 * It verifies notifications from the NovaBanka IPG and processes orders accordingly.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WC_Order;
use Exception;

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
	 * Constructor for the NotificationService class.
	 *
	 * @param APIHandler $api_handler API handler instance.
	 * @param Logger     $logger Logger instance.
	 */
	public function __construct( APIHandler $api_handler, Logger $logger ) {
		$this->api_handler = $api_handler;
		$this->logger      = $logger;
	}

	/**
	 * Handle IPG payment notification.
	 *
	 * @param array $notification_data The data received from the IPG notification.
	 * @return void
	 * @throws NovaBankaIPGException When the notification handling fails.
	 */
	public function handle_notification( array $notification_data ) {
		try {
			// Verify notification signature.
			if ( ! $this->verify_signature( $notification_data, $notification_data['msgVerifier'] ) ) {
				throw new NovaBankaIPGException( 'Invalid notification signature.' );
			}

			$order_id = $notification_data['trackid'];
			$order    = wc_get_order( $order_id );

			if ( ! $order ) {
				throw new NovaBankaIPGException( 'Order not found for ID: ' . esc_html( $order_id ) );
			}

			// Process the notification based on the result.
			switch ( $notification_data['result'] ) {
				case 'CAPTURED':
					$this->process_successful_payment( $order, $notification_data );
					$this->logger->info(
						'Payment captured successfully.',
						array(
							'order_id'          => $order_id,
							'notification_data' => $notification_data,
						)
					);
					break;
				case 'DECLINED':
					$this->process_declined_payment( $order, $notification_data );
					$this->logger->warning(
						'Payment was declined.',
						array(
							'order_id'          => $order_id,
							'notification_data' => $notification_data,
						)
					);
					break;
				case 'FAILED':
					$this->process_failed_payment( $order, $notification_data );
					$this->logger->error(
						'Payment failed.',
						array(
							'order_id'          => $order_id,
							'notification_data' => $notification_data,
						)
					);
					break;
				default:
					throw new NovaBankaIPGException( 'Unknown payment result: ' . esc_html( $notification_data['result'] ) );
			}
		} catch ( Exception $e ) {
			$this->logger->error(
				'Notification handling failed.',
				array(
					'notification_data' => $notification_data,
					'error'             => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( 'Notification handling failed: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Verify the notification signature.
	 *
	 * @param array  $notification_data The data from the notification.
	 * @param string $signature The expected signature for validation.
	 * @return bool True if the signature is valid, false otherwise.
	 */
	private function verify_signature( array $notification_data, string $signature ) {
		// Generate the expected signature using a secret key and notification data.
		$expected_signature = hash( 'sha256', json_encode( $notification_data ) . Config::get_setting( 'secret_key' ) );
		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Process successful payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function process_successful_payment( WC_Order $order, array $notification_data ) {
		$order->payment_complete( $notification_data['tranid'] );
		$order->add_order_note(
			sprintf(
				__( 'Payment completed successfully. Transaction ID: %1$s, Auth Code: %2$s', 'novabanka-ipg-gateway' ),
				$notification_data['tranid'],
				$notification_data['auth']
			)
		);
		$order->update_meta_data( '_novabankaipg_auth_code', $notification_data['auth'] );
		$order->update_meta_data( '_novabankaipg_card_type', $notification_data['cardtype'] ?? 'unknown' );
		$order->update_meta_data( '_novabankaipg_card_last4', $notification_data['cardLastFourDigits'] );
		$order->update_meta_data( '_novabankaipg_payment_reference', $notification_data['paymentReference'] ?? 'N/A' );
		$order->save();
	}

	/**
	 * Process declined payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function process_declined_payment( WC_Order $order, array $notification_data ) {
		$order->update_status(
			'on-hold',
			sprintf(
				__( 'Payment was declined. Result: %1$s, Code: %2$s', 'novabanka-ipg-gateway' ),
				$notification_data['result'],
				$notification_data['responsecode'] ?? 'N/A'
			)
		);
	}

	/**
	 * Process failed payment.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $notification_data Payment notification data.
	 * @return void
	 */
	private function process_failed_payment( WC_Order $order, array $notification_data ) {
		$order->update_status(
			'failed',
			sprintf(
				__( 'Payment failed. Result: %1$s, Code: %2$s', 'novabanka-ipg-gateway' ),
				$notification_data['result'],
				$notification_data['responsecode'] ?? 'N/A'
			)
		);
	}
}
