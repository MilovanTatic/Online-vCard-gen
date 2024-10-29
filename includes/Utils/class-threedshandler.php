<?php
/**
 * ThreeDSHandler Class
 *
 * This class is responsible for handling 3D Secure (3DS) authentication for NovaBanka IPG.
 * It manages the process of initiating and verifying 3D Secure authentication during payments.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WC_Order;
use Exception;

/**
 * Class ThreeDSHandler
 *
 * Handles 3D Secure (3DS) authentication flow for NovaBanka IPG payments.
 * Manages initiation and verification of 3DS authentication process.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class ThreeDSHandler {

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
	 * Constructor for the ThreeDSHandler class.
	 *
	 * @param APIHandler $api_handler API handler instance.
	 * @param Logger     $logger      Logger instance.
	 */
	public function __construct( APIHandler $api_handler, Logger $logger ) {
		$this->api_handler = $api_handler;
		$this->logger      = $logger;
	}

	/**
	 * Initiate 3D Secure authentication.
	 *
	 * @param WC_Order $order The order to initiate 3D Secure for.
	 * @param array    $auth_data The authentication data to be sent to IPG.
	 * @return array The response from the IPG.
	 * @throws NovaBankaIPGException When the 3D Secure initiation fails.
	 */
	public function initiate_authentication( WC_Order $order, array $auth_data ): array {
		try {
			$response = $this->api_handler->post( '/3ds/initiate', $auth_data );

			if ( 'PENDING_AUTH' !== $response['status'] ) {
				throw NovaBankaIPGException::threeDSInitiationFailed( '3D Secure initiation failed.', $response );
			}

			$this->logger->info(
				'3D Secure initiation successful.',
				array(
					'order_id' => $order->get_id(),
					'response' => $response,
				)
			);

			return $response;
		} catch ( Exception $e ) {
			NovaBankaIPGException::handle_error(
				$e,
				$this->logger,
				'3D Secure initiation',
				array(
					'order_id' => $order->get_id(),
				)
			);
		}
	}

	/**
	 * Verify 3D Secure authentication.
	 *
	 * @param WC_Order $order The order to verify 3D Secure for.
	 * @param array    $verification_data The verification data returned from IPG.
	 * @return array The response from the IPG.
	 * @throws NovaBankaIPGException When the 3D Secure verification fails.
	 */
	public function verify_authentication( WC_Order $order, array $verification_data ): array {
		try {
			$response = $this->api_handler->post( '/3ds/verify', $verification_data );

			if ( 'AUTHENTICATED' !== $response['status'] ) {
				throw NovaBankaIPGException::paymentError( '3D Secure verification failed.', $response );
			}

			$this->logger->info(
				'3D Secure authentication verified successfully.',
				array(
					'order_id' => $order->get_id(),
					'response' => $response,
				)
			);

			return $response;
		} catch ( Exception $e ) {
			NovaBankaIPGException::handle_error(
				$e,
				$this->logger,
				'3D Secure verification',
				array(
					'order_id' => $order->get_id(),
				)
			);
		}
	}

	/**
	 * Check if 3D Secure is required for the transaction.
	 *
	 * @param array $transaction_data The data associated with the current transaction.
	 * @return bool True if 3D Secure is required, false otherwise.
	 */
	public static function is_3ds_required( array $transaction_data ): bool {
		return isset( $transaction_data['threeDSRequired'] ) &&
				true === $transaction_data['threeDSRequired'];
	}

	/**
	 * Generate the URL for 3D Secure authentication.
	 *
	 * @param array $transaction_data The data associated with the current transaction.
	 * @return string The URL for 3D Secure authentication.
	 * @throws NovaBankaIPGException If 3DS URL is missing or invalid.
	 */
	public static function generate_3ds_url( array $transaction_data ): string {
		if ( empty( $transaction_data['threeDSURL'] ) ) {
			throw new NovaBankaIPGException( '3D Secure URL is missing from the transaction data.' );
		}
		return $transaction_data['threeDSURL'];
	}

	/**
	 * Handle the response from the 3D Secure process.
	 *
	 * @param array $response_data The response data from the 3D Secure authentication process.
	 * @return bool True if the 3DS authentication was successful, false otherwise.
	 * @throws NovaBankaIPGException If the response data is invalid or indicates a failure.
	 */
	public function handle_3ds_response( array $response_data ): bool {
		if ( ! isset( $response_data['status'] ) ) {
			throw NovaBankaIPGException::invalid_response( 'Missing 3DS status' );
		}

		$this->logger->info(
			'3DS Response received',
			array(
				'status' => $response_data['status'],
				'data'   => SharedUtilities::redact_sensitive_data( $response_data ),
			)
		);

		return 'SUCCESS' === strtoupper( $response_data['status'] );
	}

	/**
	 * Verify the authentication response signature for added security.
	 *
	 * @param array  $response_data The response data from the 3DS.
	 * @param string $signature The expected signature for validation.
	 * @return bool True if the signature is valid, false otherwise.
	 */
	public static function verify_3ds_signature( array $response_data, string $signature ): bool {
		$calculated_signature = hash( 'sha256', json_encode( $response_data ) . Config::get_setting( 'secret_key' ) );
		return hash_equals( $calculated_signature, $signature );
	}

	/**
	 * Prepare authentication data for 3D Secure initiation.
	 *
	 * @param WC_Order $order The order to prepare authentication data for.
	 * @param array    $auth_data The initial authentication data.
	 * @return array The prepared authentication data.
	 */
	private function prepare_auth_data( WC_Order $order, array $auth_data ): array {
		$auth_data['order_id'] = $order->get_id();
		$auth_data['amount']   = $order->get_total();
		$auth_data['currency'] = $order->get_currency();
		return $auth_data;
	}

	/**
	 * Prepare verification data for 3D Secure verification.
	 *
	 * @param WC_Order $order The order to prepare verification data for.
	 * @param array    $verification_data The initial verification data.
	 * @return array The prepared verification data.
	 */
	private function prepare_verification_data( WC_Order $order, array $verification_data ): array {
		$verification_data['order_id'] = $order->get_id();
		return $verification_data;
	}

	/**
	 * Prepare 3DS data for PaymentInit request.
	 *
	 * @param WC_Order $order The order object.
	 * @param array    $payment_data The payment data.
	 * @return array Prepared 3DS data.
	 */
	public function prepare_3ds_data( WC_Order $order, array $payment_data ): array {
		return array_merge(
			$payment_data,
			array(
				'returnUrl' => $this->get_3ds_return_url( $order ),
				'orderId'   => $order->get_id(),
				'amount'    => SharedUtilities::format_amount( $order->get_total() ),
				'currency'  => $order->get_currency(),
			)
		);
	}

	/**
	 * Get the return URL for 3DS authentication.
	 *
	 * @param WC_Order $order The order object.
	 * @return string The formatted return URL.
	 */
	private function get_3ds_return_url( WC_Order $order ): string {
		return add_query_arg(
			array(
				'wc-api'   => 'novabankaipg_3ds',
				'order_id' => $order->get_id(),
			),
			home_url( '/' )
		);
	}

	/**
	 * Prepare account information for 3DS.
	 *
	 * @param WC_Order $order The order object.
	 * @return array Account information.
	 */
	private function prepare_account_info( WC_Order $order ): array {
		$user_id      = $order->get_user_id() ?? 0;
		$account_data = array();

		if ( $user_id ) {
			$user                    = get_userdata( $user_id );
			$registration_date       = $user->user_registered;
			$days_since_registration = ( time() - strtotime( $registration_date ) ) / DAY_IN_SECONDS;

			$account_data['chAccAgeInd'] = $days_since_registration > 365 ? '05' : '02';
			$account_data['chAccDate']   = gmdate( 'Ymd', strtotime( $registration_date ) );
		}

		return $account_data;
	}
}
