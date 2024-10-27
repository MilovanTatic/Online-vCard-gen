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

use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WP_Error;

class APIHandler {
	/**
	 * Send payment initialization request to the IPG API.
	 *
	 * @param array $payment_data The data for initializing payment.
	 * @return array The response from the IPG API.
	 * @throws NovaBankaIPGException If the request fails or returns an error.
	 */
	public function send_payment_init( array $payment_data ) {
		$endpoint = Config::is_test_mode() ? Config::get_setting( 'test_api_url' ) : Config::get_setting( 'live_api_url' );
		$url      = rtrim( $endpoint, '/' ) . '/payment-init';

		$response = wp_remote_post(
			$url,
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
		$endpoint = Config::is_test_mode() ? Config::get_setting( 'test_api_url' ) : Config::get_setting( 'live_api_url' );
		$url      = rtrim( $endpoint, '/' ) . '/refund';

		$response = wp_remote_post(
			$url,
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
		// Verification logic, for example using a shared secret to validate the notification.
		$expected_signature = hash( 'sha256', json_encode( $notification_data ) . Config::get_setting( 'secret_key' ) );
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
			throw new NovaBankaIPGException( 'API request failed: ' . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			throw new NovaBankaIPGException( 'API request returned error code ' . $response_code . ': ' . json_encode( $response_body ) );
		}

		return $response_body;
	}
}
