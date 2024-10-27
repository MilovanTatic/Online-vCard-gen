<?php
/**
 * ThreeDSHandler Utility Class
 *
 * This class is responsible for managing the 3D Secure (3DS) authentication process.
 * It helps ensure that transactions comply with security standards by handling 3DS verifications.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Utils\Logger;

class ThreeDSHandler {
	/**
	 * API Handler instance
	 *
	 * @var ApiHandler
	 */
	private $api_handler;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Data Handler instance
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Constructor
	 *
	 * @param ApiHandler  $api_handler API handler instance.
	 * @param Logger      $logger Logger instance.
	 * @param DataHandler $data_handler Data handler instance.
	 */
	public function __construct( ApiHandler $api_handler, Logger $logger, DataHandler $data_handler ) {
		$this->api_handler  = $api_handler;
		$this->logger       = $logger;
		$this->data_handler = $data_handler;
	}

	/**
	 * Verify if the transaction requires 3D Secure.
	 *
	 * @param array $transaction_data The data associated with the current transaction.
	 * @return bool True if 3DS is required, false otherwise.
	 */
	public static function is_3ds_required( $transaction_data ) {
		// Check if the 3D Secure flag is set in transaction data.
		return isset( $transaction_data['threeDSRequired'] ) && $transaction_data['threeDSRequired'] === true;
	}

	/**
	 * Generate the URL required for 3D Secure authentication.
	 *
	 * @param array $transaction_data The data associated with the current transaction.
	 * @return string The URL to redirect the user for 3DS authentication.
	 * @throws NovaBankaIPGException If 3DS data is missing or invalid.
	 */
	public static function generate_3ds_url( $transaction_data ) {
		if ( empty( $transaction_data['threeDSURL'] ) ) {
			throw new NovaBankaIPGException( '3D Secure URL is missing from the transaction data.' );
		}

		// Return the URL to which the customer should be redirected.
		return $transaction_data['threeDSURL'];
	}

	/**
	 * Handle the response from the 3D Secure process.
	 *
	 * @param array $response_data The response data from the 3D Secure authentication process.
	 * @return bool True if the 3DS authentication was successful, false otherwise.
	 * @throws NovaBankaIPGException If the response data is invalid or indicates a failure.
	 */
	public static function handle_3ds_response( $response_data ) {
		// Validate the response status.
		if ( empty( $response_data['status'] ) || $response_data['status'] !== 'AUTHENTICATED' ) {
			throw new NovaBankaIPGException( '3D Secure authentication failed or returned an invalid status.' );
		}

		// If the status is AUTHENTICATED, return true indicating success.
		return true;
	}

	/**
	 * Verify the authentication response signature for added security.
	 *
	 * @param array  $response_data The response data from the 3DS.
	 * @param string $signature The expected signature for validation.
	 * @return bool True if the signature is valid, false otherwise.
	 */
	public static function verify_3ds_signature( $response_data, $signature ) {
		// Here you would implement logic to verify that the signature matches what is expected.
		// This might involve hashing response data with a shared secret and comparing.

		// Example: Compare the calculated signature with the provided one.
		$calculated_signature = hash( 'sha256', json_encode( $response_data ) . Config::get_setting( 'secret_key' ) );
		return hash_equals( $calculated_signature, $signature );
	}

	/**
	 * Prepare 3DS data for PaymentInit request.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array Prepared 3DS data.
	 */
	public function prepare_3ds_data( array $order_data ): array {
		$threeds_data = array(
			'payinst'                                 => 'VPAS', // 3DS Secure payment instrument.
			'acctInfo'                                => $this->prepare_account_info( $order_data ),
			'threeDSRequestorAuthenticationInfo'      => $this->prepare_authentication_info( $order_data ),
			'threeDSRequestorPriorAuthenticationInfo' => $this->prepare_prior_auth_info( $order_data ),
		);

		$this->logger->debug( 'Prepared 3DS data', array( 'data' => $threeds_data ) );

		return $threeds_data;
	}

	/**
	 * Prepare account information for 3DS.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array Account information.
	 */
	private function prepare_account_info( array $order_data ): array {
		$user_id      = $order_data['user_id'] ?? 0;
		$account_data = array();

		if ( $user_id ) {
			$user                    = get_userdata( $user_id );
			$registration_date       = $user->user_registered;
			$days_since_registration = ( time() - strtotime( $registration_date ) ) / DAY_IN_SECONDS;

			$account_data['chAccAgeInd'] = $days_since_registration > 365 ? '05' : '02';
			$account_data['chAccDate']   = date( 'Ymd', strtotime( $registration_date ) );
		}

		return $account_data;
	}

	/**
	 * Prepare authentication information for 3DS request.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array Authentication information.
	 */
	private function prepare_authentication_info( array $order_data ): array {
		// Implement the preparation of authentication information as per 3DS specifications.
		$auth_info = array(
			'threeDSReqAuthMethod' => '02', // Example: 02 means two-factor authentication.
		);

		return $auth_info;
	}

	/**
	 * Prepare prior authentication information for 3DS request.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array Prior authentication information.
	 */
	private function prepare_prior_auth_info( array $order_data ): array {
		// Implement the preparation of prior authentication information if available.
		$prior_auth_info = array(
			'threeDSReqPriorAuthData'   => 'ABC123', // Example data, this would come from prior transactions.
			'threeDSReqPriorAuthMethod' => '01',  // 01 indicates Frictionless flow.
		);

		return $prior_auth_info;
	}
}
