<?php
/**
 * APIHandler Utility Class
 *
 * This class is responsible for managing HTTP communication with the NovaBanka IPG API.
 * It abstracts all the API requests and responses, focusing only on interactions with the IPG endpoints.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WP_Error;
use NovaBankaIPG\Utils\APIHandlerInterface;
use LoggerInterface;
use DataHandlerInterface;

/**
 * Class APIHandler
 *
 * Handles API communication with the NovaBanka IPG payment gateway.
 * Implements APIHandlerInterface for standardized API operations.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class APIHandler implements APIHandlerInterface {
	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Data handler instance.
	 *
	 * @var DataHandlerInterface
	 */
	private $data_handler;

	/**
	 * Constructor for the APIHandler class.
	 *
	 * @param string              $api_endpoint      API endpoint URL.
	 * @param string              $terminal_id       Terminal ID.
	 * @param string              $terminal_password Terminal password.
	 * @param string              $secret_key        Secret key.
	 * @param LoggerInterface     $logger           Logger instance.
	 * @param DataHandlerInterface $data_handler     Data handler instance.
	 * @param string              $test_mode        Test mode flag.
	 */
	public function __construct(
		string $api_endpoint,
		string $terminal_id,
		string $terminal_password,
		string $secret_key,
		LoggerInterface $logger,
		DataHandlerInterface $data_handler,
		string $test_mode = 'yes'
	) {
		$this->api_endpoint = $api_endpoint;
		$this->terminal_id = $terminal_id;
		$this->terminal_password = $terminal_password;
		$this->secret_key = $secret_key;
		$this->logger = $logger;
		$this->data_handler = $data_handler;
		$this->test_mode = $test_mode;
	}

	/**
	 * Send payment initialization request to the IPG API.
	 *
	 * @param array $payment_data The data for initializing payment.
	 * @return array The response from the IPG API.
	 * @throws NovaBankaIPGException If the request fails or returns an error.
	 */
	public function send_payment_init( array $payment_data ) {
		// Validate required fields.
		SharedUtilities::validate_required_fields( $payment_data, array( 'amount', 'currency', 'order_id' ) );

		$endpoint = SharedUtilities::get_api_endpoint( '/payment-init' );

		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => json_encode( $payment_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Send refund request to the IPG API.
	 *
	 * @param array $refund_data The data for processing a refund.
	 * @return array The response from the IPG API.
	 * @throws NovaBankaIPGException If the request fails or returns an error.
	 */
	public function process_refund( array $refund_data ) {
		// Validate required fields.
		SharedUtilities::validate_required_fields( $refund_data, array( 'amount', 'order_id' ) );

		$endpoint = SharedUtilities::get_api_endpoint( '/refund' );

		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => json_encode( $refund_data ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Verify payment notification from the IPG.
	 *
	 * @param array $notification_data The data received from IPG to verify.
	 * @return bool True if notification verification is successful, false otherwise.
	 * @throws NovaBankaIPGException If the verification fails.
	 */
	public function verify_notification( array $notification_data ) {
		// Validate required fields.
		SharedUtilities::validate_required_fields( $notification_data, array( 'msgVerifier' ) );

		// Generate the expected signature.
		$expected_signature = SharedUtilities::generate_message_verifier( ...array_values( $notification_data ) );
		return hash_equals( $expected_signature, $notification_data['msgVerifier'] );
	}

	/**
	 * Handle the response from an API request.
	 *
	 * @param array|WP_Error $response The response from wp_remote_post or wp_remote_get.
	 * @return array The decoded response body.
	 * @throws NovaBankaIPGException If the response contains errors.
	 */
	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new NovaBankaIPGException( 'API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			throw new NovaBankaIPGException(
				sprintf(
					'API request returned error code %d: %s',
					esc_html( $response_code ),
					esc_html( wp_json_encode( $response_body ) )
				)
			);
		}

		return $response_body;
	}
}
