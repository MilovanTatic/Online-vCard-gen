<?php
/**
 * API Handler Class
 *
 * Handles HTTP communication with the NovaBanka IPG API.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WP_Error;

/**
 * Class APIHandler
 *
 * Handles HTTP communication with the NovaBanka IPG API.
 * Makes API requests and handles basic response validation.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class APIHandler {
	/**
	 * API endpoint URL.
	 *
	 * @var string
	 */
	private string $api_endpoint;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Success HTTP status codes.
	 *
	 * @var array
	 */
	private const SUCCESS_STATUS_CODES = array(
		200, // OK.
		201, // Created.
		202, // Accepted.
	);

	/**
	 * Constructor.
	 *
	 * @param string $api_endpoint API endpoint URL.
	 * @param Logger $logger Logger instance.
	 */
	public function __construct( string $api_endpoint, Logger $logger ) {
		$this->api_endpoint = $api_endpoint;
		$this->logger       = $logger;
	}

	/**
	 * Send a POST request to the API.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param array  $data Request data.
	 * @param int    $timeout Request timeout in seconds.
	 * @return array Response data.
	 * @throws NovaBankaIPGException If request fails.
	 */
	public function send_request( string $endpoint, array $data, int $timeout = 30 ): array {
		$url = $this->get_api_url( $endpoint );

		$this->logger->debug(
			'Sending API request',
			array(
				'endpoint' => $endpoint,
				'data'     => $data,
			)
		);

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $this->get_request_headers(),
				'body'    => wp_json_encode( $data ),
				'timeout' => $timeout,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->handle_request_error( $response );
		}

		return $this->parse_response( $response );
	}

	/**
	 * Send a GET request to the API.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param array  $params Query parameters.
	 * @return array Response data.
	 * @throws NovaBankaIPGException If request fails.
	 */
	public function get_request( string $endpoint, array $params = array() ): array {
		$url = $this->get_api_url( $endpoint );
		if ( ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => $this->get_request_headers(),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->handle_request_error( $response );
		}

		return $this->parse_response( $response );
	}

	/**
	 * Get request headers.
	 *
	 * @return array Request headers.
	 */
	private function get_request_headers(): array {
		return array(
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'Cache-Control' => 'no-cache',
		);
	}

	/**
	 * Get full API URL.
	 *
	 * @param string $endpoint API endpoint path.
	 * @return string Full API URL.
	 */
	private function get_api_url( string $endpoint ): string {
		return rtrim( $this->api_endpoint, '/' ) . '/' . ltrim( $endpoint, '/' );
	}

	/**
	 * Handle request error.
	 *
	 * @param WP_Error $error WordPress error object.
	 * @throws NovaBankaIPGException Always throws exception with error details.
	 */
	private function handle_request_error( WP_Error $error ): void {
		$this->logger->error(
			'API request failed',
			array(
				'error_message' => $error->get_error_message(),
				'error_code'    => $error->get_error_code(),
			)
		);

		throw new NovaBankaIPGException(
			'API_ERROR',
			sprintf( 'API request failed: %s', esc_html( $error->get_error_message() ) )
		);
	}

	/**
	 * Parse API response.
	 *
	 * @param array $response WordPress response array.
	 * @return array Parsed response data.
	 * @throws NovaBankaIPGException If response is invalid.
	 */
	private function parse_response( array $response ): array {
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->logger->error(
				'Invalid JSON response',
				array(
					'response'   => $body,
					'json_error' => json_last_error_msg(),
				)
			);

			throw new NovaBankaIPGException(
				'INVALID_RESPONSE',
				'Invalid JSON response from API'
			);
		}

		$this->logger->debug(
			'API response received',
			array(
				'response' => $data,
			)
		);

		return $data;
	}

	/**
	 * Check if HTTP status code indicates success.
	 *
	 * @param int $status_code HTTP status code.
	 * @return bool
	 */
	private function is_success_status( int $status_code ): bool {
		return in_array( $status_code, self::SUCCESS_STATUS_CODES, true );
	}
}
