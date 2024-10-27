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

use NovaBankaIPG\Interfaces\APIHandlerInterface;
use NovaBankaIPG\Interfaces\LoggerInterface;
use NovaBankaIPG\Interfaces\DataHandlerInterface;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WP_Error;

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
	 * Test mode flag.
	 *
	 * @var string
	 */
	private $test_mode;

	/**
	 * Constructor for the APIHandler class.
	 *
	 * @param string               $api_endpoint      API endpoint URL.
	 * @param string               $terminal_id       Terminal ID.
	 * @param string               $terminal_password Terminal password.
	 * @param string               $secret_key        Secret key.
	 * @param LoggerInterface      $logger           Logger instance.
	 * @param DataHandlerInterface $data_handler     Data handler instance.
	 * @param string               $test_mode        Test mode flag.
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
		$this->api_endpoint      = $api_endpoint;
		$this->terminal_id       = $terminal_id;
		$this->terminal_password = $terminal_password;
		$this->secret_key        = $secret_key;
		$this->logger            = $logger;
		$this->data_handler      = $data_handler;
		$this->test_mode         = $test_mode;
	}

	/**
	 * Send payment initialization request to the IPG API.
	 *
	 * @param array $data The data for initializing payment.
	 * @return array The response from the IPG API.
	 * @throws NovaBankaIPGException If the request fails or returns an error.
	 */
	public function send_payment_init( array $data ): array {
		$this->logger->debug( 'Initializing payment request', array( 'request_data' => $data ) );

		try {
			SharedUtilities::validate_required_fields( $data, array( 'amount', 'currency', 'order_id' ) );

			$endpoint     = SharedUtilities::get_api_endpoint( '/payment-init' );
			$request_data = $this->prepare_request_data( $data );

			$this->logger->debug(
				'Sending payment request',
				array(
					'endpoint'     => $endpoint,
					'request_data' => $request_data,
				)
			);

			$response = $this->make_request( $endpoint, $request_data );

			$this->logger->debug( 'Payment request successful', array( 'response' => $response ) );
			return $response;

		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'Payment initialization failed',
				array(
					'error'        => $e->getMessage(),
					'request_data' => $data,
				)
			);
			throw $e;
		}
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
		$this->logger->debug( 'Verifying notification', array( 'notification' => $notification_data ) );

		try {
			SharedUtilities::validate_required_fields( $notification_data, array( 'msgVerifier' ) );

			$expected_signature = SharedUtilities::generate_message_verifier(
				...array_values( $notification_data )
			);

			$is_valid = hash_equals( $expected_signature, $notification_data['msgVerifier'] );

			$this->logger->debug(
				'Notification verification result',
				array(
					'is_valid'     => $is_valid,
					'notification' => $notification_data,
				)
			);

			return $is_valid;

		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'Notification verification failed',
				array(
					'error'        => $e->getMessage(),
					'notification' => $notification_data,
				)
			);
			throw $e;
		}
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
			$this->logger->error(
				'API request failed',
				array(
					'error'      => $response->get_error_message(),
					'error_code' => $response->get_error_code(),
				)
			);
			throw new NovaBankaIPGException( 'API request failed: ' . esc_html( $response->get_error_message() ) );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->logger->debug(
			'API response received',
			array(
				'code'    => $response_code,
				'body'    => $response_body,
				'headers' => wp_remote_retrieve_headers( $response ),
			)
		);

		if ( $response_code < 200 || $response_code >= 300 ) {
			$this->logger->error(
				'API error response',
				array(
					'code' => $response_code,
					'body' => $response_body,
				)
			);
			throw new NovaBankaIPGException(
				sprintf(
					'API request failed with code %d: %s',
					esc_html( $response_code ),
					esc_html( wp_json_encode( $response_body ) )
				)
			);
		}

		return $response_body;
	}

	/**
	 * Generate notification response
	 *
	 * @param string $payment_id  Payment ID.
	 * @param string $redirect_url Redirect URL.
	 * @return array
	 */
	public function generate_notification_response( string $payment_id, string $redirect_url ): array {
		$this->logger->debug(
			'Generating notification response',
			array(
				'payment_id'   => $payment_id,
				'redirect_url' => $redirect_url,
			)
		);

		$response = array(
			'paymentId'   => $payment_id,
			'redirectUrl' => $redirect_url,
			'timestamp'   => time(),
			'msgVerifier' => SharedUtilities::generate_message_verifier( $payment_id, $redirect_url ),
		);

		$this->logger->debug( 'Generated notification response', array( 'response' => $response ) );
		return $response;
	}

	/**
	 * Set API configuration
	 *
	 * @param array $config API configuration.
	 * @return void
	 */
	public function set_config( array $config ): void {
		$this->logger->debug( 'Updating API configuration', array( 'new_config' => $config ) );

		$this->api_endpoint      = $config['api_endpoint'] ?? $this->api_endpoint;
		$this->terminal_id       = $config['terminal_id'] ?? $this->terminal_id;
		$this->terminal_password = $config['terminal_password'] ?? $this->terminal_password;
		$this->secret_key        = $config['secret_key'] ?? $this->secret_key;
		$this->test_mode         = $config['test_mode'] ?? $this->test_mode;

		$this->logger->debug( 'API configuration updated' );
	}

	/**
	 * Prepare request data
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	private function prepare_request_data( array $data ): array {
		return array_merge(
			$data,
			array(
				'terminal_id' => $this->terminal_id,
				'timestamp'   => time(),
			)
		);
	}

	/**
	 * Make a request to the API
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @return array
	 */
	private function make_request( string $endpoint, array $data ): array {
		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => wp_json_encode( $data ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'X-Terminal-ID' => $this->terminal_id,
					'X-Test-Mode'   => $this->test_mode,
				),
				'timeout' => 30,
			)
		);

		return $this->handle_response( $response );
	}
}
