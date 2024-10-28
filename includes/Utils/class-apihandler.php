<?php
/**
 * APIHandler Utility Class
 *
 * Handles HTTP communication with the NovaBanka IPG API according to integration guide.
 * Manages request/response formatting, message verification, and error handling.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WP_Error;

/**
 * Handles raw HTTP communication with the IPG API.
 */
class APIHandler {
	/**
	 * API endpoint URL.
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * Terminal ID.
	 *
	 * @var string
	 */
	private $terminal_id;

	/**
	 * Terminal password.
	 *
	 * @var string
	 */
	private $terminal_password;

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	private $secret_key;

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
	 * Test mode flag.
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Constructor.
	 *
	 * @param string      $api_endpoint      API endpoint URL.
	 * @param string      $terminal_id       Terminal ID.
	 * @param string      $terminal_password Terminal password.
	 * @param string      $secret_key        Secret key for message verification.
	 * @param Logger      $logger            Logger instance.
	 * @param DataHandler $data_handler      Data handler instance.
	 * @param string      $test_mode         Test mode flag.
	 */
	public function __construct(
		string $api_endpoint,
		string $terminal_id,
		string $terminal_password,
		string $secret_key,
		Logger $logger,
		DataHandler $data_handler,
		string $test_mode = 'yes'
	) {
		$this->api_endpoint      = $api_endpoint;
		$this->terminal_id       = $terminal_id;
		$this->terminal_password = $terminal_password;
		$this->secret_key        = $secret_key;
		$this->logger            = $logger;
		$this->data_handler      = $data_handler;
		$this->test_mode         = 'yes' === $test_mode;
	}

	/**
	 * Send HTTP request to IPG API.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @return array Response data.
	 * @throws NovaBankaIPGException When HTTP request fails.
	 */
	public function send_request( string $endpoint, array $data ): array {
		try {
			// Send request.
			$response = wp_remote_post(
				$this->get_api_url( $endpoint ),
				array(
					'headers'   => array(
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					),
					'body'      => wp_json_encode( $data ),
					'timeout'   => 30,
					'sslverify' => true,
				)
			);

			// Handle WP_Error.
			if ( is_wp_error( $response ) ) {
				throw new NovaBankaIPGException( esc_html( $response->get_error_message() ) );
			}

			// Get response code and body.
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			// Handle non-200 responses.
			if ( $code < 200 || $code >= 300 ) {
				throw new NovaBankaIPGException( sprintf( 'HTTP error: %d', $code ) );
			}

			// Decode JSON response.
			$decoded = json_decode( $body, true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				throw new NovaBankaIPGException( 'Invalid JSON response.' );
			}

			return $decoded;

		} catch ( Exception $e ) {
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Get full API URL.
	 *
	 * @param string $endpoint API endpoint.
	 * @return string Full API URL.
	 */
	private function get_api_url( string $endpoint ): string {
		return trailingslashit( $this->api_endpoint ) . ltrim( $endpoint, '/' );
	}

	/**
	 * Redact sensitive data for logging.
	 *
	 * @param array $data Data to redact.
	 * @return array Redacted data.
	 */
	private function redact_sensitive_data( array $data ): array {
		$sensitive_fields = array( 'password', 'terminal_password', 'secret_key' );
		foreach ( $sensitive_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = '***REDACTED***';
			}
		}
		return $data;
	}

	/**
	 * Send payment initialization request to IPG.
	 *
	 * @param array $init_data Payment initialization data.
	 * @return array Response from IPG.
	 * @throws NovaBankaIPGException When request fails.
	 */
	public function send_payment_init_request( array $init_data ): array {
		try {
			// Log request data for debugging.
			$this->logger->debug(
				'Sending payment init request.',
				array(
					'endpoint' => $this->get_api_url( 'PaymentInitRequest' ),
					'data'     => $this->redact_sensitive_data( $init_data ),
				)
			);

			// Add terminal credentials and required fields.
			$request_data = array_merge(
				$init_data,
				array(
					'msgName'  => 'PaymentInitRequest',
					'version'  => '1',
					'id'       => $this->terminal_id,
					'password' => $this->terminal_password,
					'action'   => '1', // 1 for payment init
				)
			);

			// Generate message verifier.
			$verifier_fields = array(
				$request_data['msgName'],
				$request_data['version'],
				$request_data['id'],
				$request_data['password'],
				$request_data['amt'],
				$request_data['trackid'],
				$request_data['udf1'] ?? '',
				$this->secret_key,
				$request_data['udf5'] ?? '',
			);

			// Add message verifier to request.
			$request_data['msgVerifier'] = base64_encode(
				hash( 'sha256', implode( '', $verifier_fields ), true )
			);

			// Send request to PaymentInit endpoint.
			$response = $this->send_request( 'PaymentInitRequest', $request_data );

			// Log full response for debugging.
			$this->logger->debug(
				'Received payment init response.',
				array(
					'response' => $this->redact_sensitive_data( $response ),
				)
			);
			// Check for error response.
			if ( isset( $response['type'] ) && 'error' === $response['type'] ) {
				throw new NovaBankaIPGException(
					sprintf(
						'IPG Error: %s - %s',
						$response['errorCode'],
						$response['errorDesc']
					)
				);
			}

			// Validate response.
			if ( empty( $response['browserRedirectionURL'] ) ) {
				throw new NovaBankaIPGException(
					sprintf(
						'Missing browserRedirectionURL in response. Response: %s',
						wp_json_encode( $this->redact_sensitive_data( $response ) )
					)
				);
			}

			return $response;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Payment initialization failed.',
				array(
					'error'    => $e->getMessage(),
					'data'     => $this->redact_sensitive_data( $init_data ),
					'response' => isset( $response ) ? $this->redact_sensitive_data( $response ) : null,
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}
}
