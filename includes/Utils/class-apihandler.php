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
	 * @param string $path API endpoint path.
	 * @param array  $data Request data.
	 * @return array Response data.
	 * @throws NovaBankaIPGException If request fails.
	 */
	public function post( string $path, array $data ): array {
		$url      = $this->api_endpoint . $path;
		$response = wp_remote_post(
			$url,
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'timeout' => 30,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * Handle API response.
	 *
	 * @param mixed $response API response.
	 * @return array Response data.
	 * @throws NovaBankaIPGException If response is invalid.
	 */
	private function handle_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			throw NovaBankaIPGException::api_error( $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( ! in_array( $status_code, self::SUCCESS_STATUS_CODES, true ) ) {
			throw NovaBankaIPGException::api_error(
				sprintf( 'API request failed with status code: %d', $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw NovaBankaIPGException::invalid_response( 'Invalid JSON response' );
		}

		return $data;
	}
}
