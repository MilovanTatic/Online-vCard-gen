<?php
/**
 * API Handler Class
 *
 * This class is responsible for all HTTP communications with the NovaBanka IPG API.
 * It handles sending requests, receiving responses, and signature verification.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use Exception;
use WP_Http;

/**
 * Class APIHandler
 *
 * Handles API communication with the NovaBanka IPG API.
 */
class APIHandler {
	/**
	 * API Endpoint URL.
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://ipgtest.novabanka.com/IPGWeb/servlet/';

	/**
	 * Send payment initialization request.
	 *
	 * @param array $data Payment initialization data.
	 * @return array Response from the payment gateway.
	 * @throws Exception When the request fails.
	 */
	public function send_payment_init( array $data ) {
		$url  = $this->api_endpoint . 'PaymentInit';
		$args = array(
			'body'    => wp_json_encode( $data ),
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'timeout' => 45,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Payment initialization request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			throw new Exception( 'Unexpected response code: ' . $response_code );
		}

		$body          = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new Exception( 'Failed to decode response JSON.' );
		}

		return $response_data;
	}

	/**
	 * Verify the notification signature.
	 *
	 * @param array  $notification_data Notification data received from IPG.
	 * @param string $expected_signature Expected signature value.
	 * @return bool True if the signature matches, false otherwise.
	 */
	public function verify_signature( array $notification_data, string $expected_signature ) {
		// Generate the expected signature. For now, using a simple hash as an example.
		$data_string         = implode( '|', $notification_data );
		$generated_signature = hash( 'sha256', $data_string );

		// Compare with the expected signature.
		return hash_equals( $generated_signature, $expected_signature );
	}
}
