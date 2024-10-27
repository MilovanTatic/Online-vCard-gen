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

use NovaBankaIPG\Interfaces\ThreeDSHandlerInterface;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WC_Order;
use Exception;

class ThreeDSHandler implements ThreeDSHandlerInterface {

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
	 * @param APIHandlerInterface $api_handler API handler instance.
	 * @param LoggerInterface     $logger      Logger instance.
	 */
	public function __construct(APIHandlerInterface $api_handler, LoggerInterface $logger) {
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
	public function initiate_3ds( WC_Order $order, array $auth_data ): array {
		try {
			// Prepare the 3DS request data.
			$auth_data = $this->prepare_auth_data( $order, $auth_data );

			// Log the initiation request if in debug mode.
			if ( Config::get_setting( 'debug', false ) ) {
				$this->logger->debug( 'Initiating 3D Secure authentication', array( 'auth_data' => $auth_data ) );
			}

			// Send the 3DS initiation request to the IPG.
			$response = $this->api_handler->send_3ds_initiation( $auth_data );

			// Handle the response from IPG.
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
		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'3D Secure initiation failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw $e;
		} catch ( Exception $e ) {
			$this->logger->error(
				'3D Secure initiation failed due to an unexpected error.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( '3D Secure initiation failed: ' . esc_html( $e->getMessage() ) );
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
	public function verify_3ds( WC_Order $order, array $verification_data ): array {
		try {
			// Prepare verification data.
			$verification_data = $this->prepare_verification_data( $order, $verification_data );

			// Log verification data if in debug mode.
			if ( Config::get_setting( 'debug', false ) ) {
				$this->logger->debug( 'Verifying 3D Secure authentication', array( 'verification_data' => $verification_data ) );
			}

			// Send the verification request to the IPG.
			$response = $this->api_handler->verify_3ds_authentication( $verification_data );

			// Handle the response from IPG.
			if ( 'AUTHENTICATED' === $response['status'] ) {
				$this->logger->info(
					'3D Secure authentication verified successfully.',
					array(
						'order_id' => $order->get_id(),
						'response' => $response,
					)
				);
				return $response;
			} else {
				throw NovaBankaIPGException::paymentError( '3D Secure verification failed.', $response );
			}
		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'3D Secure verification failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw $e;
		} catch ( Exception $e ) {
			$this->logger->error(
				'3D Secure verification failed due to an unexpected error.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( '3D Secure verification failed: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Check if 3D Secure is required for the transaction.
	 *
	 * @param array $transaction_data The data associated with the current transaction.
	 * @return bool True if 3D Secure is required, false otherwise.
	 */
	public static function is_3ds_required( array $transaction_data ): bool {
		return isset( $transaction_data['threeDSRequired'] ) && $transaction_data['threeDSRequired'] === true;
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
	public static function handle_3ds_response( array $response_data ): bool {
		if ( empty( $response_data['status'] ) || $response_data['status'] !== 'AUTHENTICATED' ) {
			throw new NovaBankaIPGException( '3D Secure authentication failed or returned an invalid status.' );
		}
		return true;
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
	 * @param array $order_data Order and customer data.
	 * @return array Prepared 3DS data.
	 */
	public function prepare_3ds_data( array $order_data ): array {
		$threeds_data = array(
			'payinst'                                 => 'VPAS',
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
			$account_data['chAccDate']   = gmdate( 'Ymd', strtotime( $registration_date ) );
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
		$prior_auth_info = array(
			'threeDSReqPriorAuthData'   => 'ABC123', // Example data, this would come from prior transactions.
			'threeDSReqPriorAuthMethod' => '01',  // 01 indicates Frictionless flow.
		);
		return $prior_auth_info;
	}
}
