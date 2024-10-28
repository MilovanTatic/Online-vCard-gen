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
 * Class APIHandler
 *
 * Handles API communication with the NovaBanka IPG payment gateway.
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
	 * @var string
	 */
	private $test_mode;

	/**
	 * Constructor.
	 *
	 * @param string      $api_endpoint      API endpoint URL.
	 * @param string      $terminal_id       Terminal ID.
	 * @param string      $terminal_password Terminal password.
	 * @param string      $secret_key        Secret key.
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
		$this->test_mode         = $test_mode;
	}

	/**
	 * Send payment initialization request to IPG.
	 *
	 * @param array $data Payment initialization data.
	 * @return array Response from IPG.
	 * @throws NovaBankaIPGException When request fails.
	 */
	public function send_payment_init_request( array $data ): array {
		try {
			// Allow plugins to modify request data.
			$data = apply_filters( 'novabankaipg_before_payment_init', $data );

			// Prepare request according to IPG guide.
			$request_data = array(
				'msgName'      => 'PaymentInitRequest',
				'version'      => '1',
				'id'           => $this->terminal_id,
				'password'     => $this->terminal_password,
				'action'       => '1',
				'amt'          => $this->data_handler->format_amount( $data['amount'] ),
				'currencycode' => $this->data_handler->get_currency_code( $data['currency'] ),
				'trackid'      => $data['order_id'],
				'responseURL'  => $data['responseURL'],
				'errorURL'     => $data['errorURL'],
				'langid'       => $data['langid'] ?? 'EN',
			);

			// Add message verifier.
			$request_data['msgVerifier'] = SharedUtilities::generate_message_verifier(
				$request_data['msgName'],
				$request_data['version'],
				$request_data['id'],
				$request_data['password'],
				$request_data['amt'],
				$request_data['trackid'],
				$data['udf1'] ?? '',
				$this->secret_key,
				$data['udf5'] ?? ''
			);

			$this->logger->debug(
				'Sending payment init request',
				array(
					'request' => $this->redact_sensitive_data( $request_data ),
				)
			);

			// Send request to IPG.
			$response = $this->make_request( '/payment-init', $request_data );

			// Verify response signature.
			$this->verify_response_signature( $response );

			// Allow plugins to modify response.
			return apply_filters( 'novabankaipg_after_payment_init', $response, $data );

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Payment init request failed',
				array(
					'error' => esc_html( $e->getMessage() ),
					'data'  => $this->redact_sensitive_data( $data ),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Send refund request to IPG.
	 *
	 * @param array $refund_data Refund request data.
	 * @return array Response from IPG.
	 * @throws NovaBankaIPGException When request fails.
	 */
	public function send_refund_request( array $refund_data ): array {
		try {
			// Allow plugins to modify refund data.
			$refund_data = apply_filters( 'novabankaipg_before_refund_request', $refund_data );

			// Prepare refund request according to IPG guide.
			$request_data = array(
				'msgName'  => 'RefundRequest',
				'version'  => '1',
				'id'       => $this->terminal_id,
				'password' => $this->terminal_password,
				'action'   => '2', // 2 = Refund.
				'amt'      => $this->data_handler->format_amount( $refund_data['amount'] ),
				'trackid'  => $refund_data['order_id'],
				'udf1'     => $refund_data['reason'],
			);

			// Add message verifier.
			$request_data['msgVerifier'] = SharedUtilities::generate_message_verifier(
				$request_data['msgName'],
				$request_data['version'],
				$request_data['id'],
				$request_data['password'],
				$request_data['amt'],
				$request_data['trackid'],
				$request_data['udf1'],
				$this->secret_key
			);

			$this->logger->debug(
				'Sending refund request',
				array(
					'request' => $this->redact_sensitive_data( $request_data ),
				)
			);

			// Send request to IPG.
			$response = $this->make_request( '/refund', $request_data );

			// Verify response signature.
			$this->verify_response_signature( $response );

			// Allow plugins to modify response.
			return apply_filters( 'novabankaipg_after_refund_request', $response, $refund_data );

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Refund request failed',
				array(
					'error' => esc_html( $e->getMessage() ),
					'data'  => $this->redact_sensitive_data( $refund_data ),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Make HTTP request to IPG.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param array  $data     Request data.
	 * @return array Response data.
	 * @throws NovaBankaIPGException When request fails.
	 */
	private function make_request( string $endpoint, array $data ): array {
		$url = rtrim( $this->api_endpoint, '/' ) . '/' . ltrim( $endpoint, '/' );

		$response = wp_remote_post(
			$url,
			array(
				'body'      => wp_json_encode( $data ),
				'headers'   => array(
					'Content-Type'  => 'application/json',
					'X-Terminal-ID' => $this->terminal_id,
					'X-Test-Mode'   => $this->test_mode,
				),
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new NovaBankaIPGException(
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'API request failed: %s', 'novabanka-ipg-gateway' ),
					esc_html( $response->get_error_message() )
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code < 200 || $response_code >= 300 ) {
			throw new NovaBankaIPGException(
				sprintf(
					/* translators: %1$d: response code, %2$s: response body */
					esc_html__( 'API request failed with code %1$d: %2$s', 'novabanka-ipg-gateway' ),
					esc_html( $response_code ),
					esc_html( wp_json_encode( $response_body ) )
				)
			);
		}

		return $response_body;
	}

	/**
	 * Verify response signature from IPG.
	 *
	 * @param array $response Response data to verify.
	 * @throws NovaBankaIPGException When signature verification fails.
	 */
	private function verify_response_signature( array $response ): void {
		if ( empty( $response['msgVerifier'] ) ) {
			throw new NovaBankaIPGException( esc_html__( 'Missing message verifier in response.', 'novabanka-ipg-gateway' ) );
		}

		$verifier_fields = array(
			$response['msgName'],
			$response['version'],
			$response['id'] ?? '',
			$response['amt'] ?? '',
			$response['trackid'] ?? '',
			$this->secret_key,
		);

		$calculated_verifier = SharedUtilities::generate_message_verifier( ...$verifier_fields );

		if ( ! hash_equals( $calculated_verifier, $response['msgVerifier'] ) ) {
			throw new NovaBankaIPGException( esc_html__( 'Invalid response signature.', 'novabanka-ipg-gateway' ) );
		}
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
}
