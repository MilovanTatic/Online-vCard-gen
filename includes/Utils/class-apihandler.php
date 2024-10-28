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
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param string $api_endpoint      API endpoint URL.
	 * @param Logger $logger            Logger instance.
	 */
	public function __construct(
		string $api_endpoint,
		Logger $logger
	) {
		$this->api_endpoint = $api_endpoint;
		$this->logger       = $logger;
	}

	/**
	 * Send a POST request to the IPG API.
	 *
	 * @param string $endpoint API endpoint path
	 * @param array  $data Request data
	 * @return array Response data
	 * @throws NovaBankaIPGException
	 */
	public function post( string $endpoint, array $data ): array {
		$url = rtrim( $this->api_endpoint, '/' ) . '/' . ltrim( $endpoint, '/' );

		$this->logger->debug(
			'Sending API request',
			array(
				'url'  => $url,
				'data' => $this->redact_sensitive_data( $data ),
			)
		);

		$response = wp_remote_post(
			$url,
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

		if ( is_wp_error( $response ) ) {
			throw new NovaBankaIPGException( $response->get_error_message() );
		}

		return $this->handle_response( $response );
	}

	/**
	 * Process API response.
	 *
	 * @param array $response WordPress HTTP API response
	 * @return array Decoded response data
	 * @throws NovaBankaIPGException
	 */
	private function handle_response( array $response ): array {
		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$this->logger->debug(
			'API response received',
			array(
				'code' => $response_code,
				'body' => $response_body,
			)
		);

		$decoded_body = json_decode( $response_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new NovaBankaIPGException(
				sprintf(
					'Invalid JSON response (code %d): %s',
					$response_code,
					wp_strip_all_tags( $response_body )
				)
			);
		}

		if ( $response_code < 200 || $response_code >= 300 || ! is_array( $decoded_body ) ) {
			throw new NovaBankaIPGException(
				sprintf(
					'API request failed with code %d: %s',
					$response_code,
					wp_json_encode( $decoded_body ) ?: $response_body
				)
			);
		}

		return $decoded_body;
	}

	/**
	 * Redact sensitive data for logging.
	 *
	 * @param array $data Data to redact
	 * @return array Redacted data
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
}
