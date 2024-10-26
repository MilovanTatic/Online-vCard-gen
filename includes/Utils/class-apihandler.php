<?php
/**
 * API Handler Implementation
 *
 * @package     NovaBankaIPG\Utils
 * @since       1.0.0
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use NovaBankaIPG\Interfaces\APIHandlerInterface;
use NovaBankaIPG\Interfaces\Logger;
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\MessageHandler;

defined( 'ABSPATH' ) || exit;

/**
 * API Handler Class
 *
 * Handles all API communications with the IPG gateway.
 *
 * @since 1.0.0
 */
class APIHandler implements APIHandlerInterface {
	/**
	 * API endpoint URL
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * Terminal ID
	 *
	 * @var string
	 */
	private $terminal_id;

	/**
	 * Terminal password
	 *
	 * @var string
	 */
	private $terminal_password;

	/**
	 * Secret key for message verification
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Data handler instance
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Test mode flag
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Message handler instance
	 *
	 * @var MessageHandler
	 */
	private $message_handler;

	/**
	 * Constructor.
	 *
	 * @param string      $api_endpoint      API endpoint URL.
	 * @param string      $terminal_id       Terminal ID.
	 * @param string      $terminal_password Terminal password.
	 * @param string      $secret_key        Secret key.
	 * @param Logger      $logger           Logger instance.
	 * @param DataHandler $data_handler     Data handler instance.
	 * @param bool        $test_mode         Test mode flag.
	 */
	public function __construct(
		string $api_endpoint,
		string $terminal_id,
		string $terminal_password,
		string $secret_key,
		Logger $logger,
		DataHandler $data_handler,
		bool $test_mode
	) {
		$this->api_endpoint      = $api_endpoint;
		$this->terminal_id       = $terminal_id;
		$this->terminal_password = $terminal_password;
		$this->secret_key        = $secret_key;
		$this->logger            = $logger;
		$this->data_handler      = $data_handler;
		$this->test_mode         = $test_mode;
		$this->message_handler   = new MessageHandler(
			$this->terminal_id,
			$this->terminal_password,
			$this->secret_key,
			$this->data_handler,
			$this->logger
		);
	}

	/**
	 * Send payment initialization request.
	 *
	 * @param array $data Payment initialization data.
	 * @return array Response data from the payment initialization request.
	 * @throws NovaBankaIPGException When the payment initialization fails.
	 */
	public function send_payment_init( array $data ): array {
		try {
			$payment_init = $this->prepare_payment_init_request( $data );

			$this->log_debug( 'Sending PaymentInit request.', array( 'request' => $payment_init ) );

			$response = $this->send_request( $payment_init );

			$this->log_debug( 'PaymentInit response received.', array( 'response' => $response ) );

			if ( ! isset( $response['type'] ) || 'valid' !== $response['type'] ) {
				throw NovaBankaIPGException::apiError(
					isset( $response['errorDesc'] ) ? esc_html( $response['errorDesc'] ) : 'Invalid gateway response.',
					$response
				);
			}

			return $response;

		} catch ( \Exception $e ) {
			throw new NovaBankaIPGException(
				esc_html( $e->getMessage() ),
				'API_ERROR',
				esc_html( wp_json_encode( $data ) ),
				$e
			);
		}
	}

	/**
	 * Handle payment notification
	 *
	 * @param array $notification Payment notification data.
	 * @return array Response data from the payment notification.
	 * @throws NovaBankaIPGException When the payment notification fails.
	 */
	public function handle_notification( array $notification ): array {
		try {
			$this->log_debug( 'Processing payment notification.', array( 'notification' => $notification ) );

			// Verify notification signature.
			if ( ! $this->verify_signature( $notification, $notification['msgVerifier'] ) ) {
				throw NovaBankaIPGException::invalidSignature( 'Invalid notification signature.' );
			}

			$response = array(
				'msgName'               => 'PaymentNotificationResponse',
				'version'               => '1',
				'paymentID'             => $notification['paymentid'],
				'browserRedirectionURL' => $notification['responseURL'],
			);

			// Generate response signature.
			$response['msgVerifier'] = $this->generate_message_verifier(
				array(
					$response['msgName'],
					$response['version'],
					$response['paymentID'],
					$this->secret_key,
					$response['browserRedirectionURL'],
				)
			);

			$this->log_debug( 'Notification response prepared.', array( 'response' => $response ) );

			return $response;

		} catch ( \Exception $e ) {
			throw new NovaBankaIPGException(
				esc_html( $e->getMessage() ),
				'NOTIFICATION_ERROR',
				esc_html( wp_json_encode( $notification ) ),
				esc_html( wp_json_encode( $e ) )
			);
		}
	}

	/**
	 * Process refund request
	 *
	 * @param array $data Refund request data.
	 * @return array Response data from the refund request.
	 * @throws NovaBankaIPGException When the refund request fails.
	 */
	public function process_refund( array $data ): array {
		try {
			$refund_request = $this->prepare_refund_request( $data );

			$this->log_debug( 'Sending refund request.', array( 'request' => $refund_request ) );

			$response = $this->send_request( $refund_request );

			$this->log_debug( 'Refund response received.', array( 'response' => $response ) );

			if ( 'valid' !== $response['type'] ) {
				throw NovaBankaIPGException::apiError(
					isset( $response['errorDesc'] ) ? esc_html( $response['errorDesc'] ) : 'Invalid refund response.',
					$response
				);
			}

			// Verify response signature.
			if ( ! $this->verify_signature( $response, $response['msgVerifier'] ) ) {
				throw NovaBankaIPGException::invalidSignature( 'Invalid refund response signature.' );
			}

			return $response;

		} catch ( \Exception $e ) {
			throw new NovaBankaIPGException(
				esc_html( $e->getMessage() ),
				'REFUND_FAILED',
				esc_html( wp_json_encode( $data ) ),
				esc_html( wp_json_encode( $e ) )
			);
		}
	}

	/**
	 * Send request to gateway.
	 *
	 * @param array $data Request data.
	 * @return array
	 * @throws NovaBankaIPGException If the request to the gateway fails or the response is invalid.
	 */
	private function send_request( array $data ): array {
		// Get endpoint based on msgName from request data.
		$endpoint = $this->get_api_endpoint( $data['msgName'] ?? 'PaymentInitRequest' );

		$this->log_debug(
			'Sending API request.',
			array(
				'endpoint' => $endpoint,
				'data'     => $data,
				'headers'  => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
			)
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => wp_json_encode( $data ),
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log_debug(
				'API request failed.',
				array(
					'error'    => $error_message,
					'wp_error' => $response->get_error_codes(),
				)
			);
			throw NovaBankaIPGException::apiError(
				sprintf( 'Gateway connection failed: %s.', esc_html( $error_message ) )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$headers     = wp_remote_retrieve_headers( $response );
		$body        = wp_remote_retrieve_body( $response );

		$this->log_debug(
			'API response received.',
			array(
				'status_code' => $status_code,
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		$result = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_debug(
				'Invalid JSON response.',
				array(
					'json_error' => json_last_error_msg(),
					'raw_body'   => $body,
				)
			);
			throw NovaBankaIPGException::apiError( 'Invalid JSON response from gateway.' );
		}

		return $result;
	}

	/**
	 * Prepare payment initialization request
	 *
	 * @param array $data Request data.
	 * @return array
	 * @throws NovaBankaIPGException When track ID is missing.
	 */
	private function prepare_payment_init_request( array $data ): array {
		if ( empty( $data['trackid'] ) ) {
			throw new NovaBankaIPGException( 'Track ID is required' );
		}

		// Basic request structure.
		$request = array(
			'msgName'            => 'PaymentInitRequest',
			'version'            => '1',
			'id'                 => $this->terminal_id,
			'password'           => $this->terminal_password,
			'action'             => '1',
			'currencycode'       => $this->data_handler->get_currency_code( $data['currency'] ),
			'amt'                => $this->format_amount( $data['amount'] ),
			'trackid'            => (string) $data['trackid'],
			'responseURL'        => $data['response_url'],
			'errorURL'           => $data['error_url'],
			'langid'             => $data['language'],
			'notificationFormat' => 'json',
			'payinst'            => 'VPAS',
			'recurAction'        => '',
		);

		// Add optional fields if present.
		if ( ! empty( $data['email'] ) ) {
			$request['buyerEmailAddress'] = $data['email'];
		}

		// Add UDF fields.
		foreach ( array( 'udf1', 'udf2', 'udf3' ) as $udf ) {
			if ( ! empty( $data[ $udf ] ) ) {
				$request[ $udf ] = $data[ $udf ];
			}
		}

		// Generate message verifier.
		$request['msgVerifier'] = $this->generate_message_verifier(
			array(
				$request['msgName'],
				$request['version'],
				$request['id'],
				$request['password'],
				$request['amt'],
				$request['trackid'],
				$request['udf1'] ?? '',
				$this->secret_key,
				$request['udf5'] ?? '',
			)
		);

		return $request;
	}

	/**
	 * Prepare refund request
	 *
	 * @param array $data Refund data.
	 * @return array
	 */
	private function prepare_refund_request( array $data ): array {
		$request = array(
			'msgName'      => 'FinancialRequest',
			'version'      => '1',
			'id'           => $this->terminal_id,
			'password'     => $this->terminal_password,
			'action'       => '2', // Credit/Refund.
			'tranid'       => $data['transaction_id'],
			'amt'          => $this->data_handler->formatAmount( $data['amount'] ),
			'trackid'      => $data['order_id'],
			'currencycode' => $data['currency'],
		);

		// Add message verifier.
		$request['msgVerifier'] = $this->generate_message_verifier(
			array(
				$request['msgName'],
				$request['version'],
				$request['id'],
				$request['password'],
				$request['tranid'],
				$request['amt'],
				$request['trackid'],
				$this->secret_key,
			)
		);

		return $request;
	}

	/**
	 * Verify message signature
	 *
	 * @param array  $data Data to verify.
	 * @param string $message_type Type of message being verified.
	 * @return bool
	 */
	public function verify_message_signature( array $data, string $message_type = '' ): bool {
		if ( ! isset( $data['msgVerifier'] ) ) {
			return false;
		}

		$signature = $data['msgVerifier'];
		$fields    = $this->get_verification_fields( $data, $message_type );

		$calculated = $this->generate_message_verifier( $fields );
		return hash_equals( $signature, $calculated );
	}

	/**
	 * Verify IPG notification
	 *
	 * @param array $notification Notification data.
	 * @return bool
	 */
	public function verify_notification( array $notification ): bool {
		return $this->verify_message_signature( $notification, 'PaymentNotificationRequest' );
	}

	/**
	 * Generate a response for the notification
	 *
	 * @param string $payment_id Payment ID.
	 * @param string $redirect_url Redirect URL.
	 * @return array
	 */
	public function generate_notification_response( string $payment_id, string $redirect_url ): array {
		// Generate a response for the notification.
		return array(
			'msgName'               => 'PaymentNotificationResponse',
			'version'               => '1',
			'paymentID'             => $payment_id,
			'browserRedirectionURL' => $redirect_url,
		);
	}

	/**
	 * Set configuration values
	 *
	 * @param array $config Configuration values.
	 */
	public function set_config( array $config ): void {
		// Set configuration values.
		if ( isset( $config['api_endpoint'] ) ) {
			$this->api_endpoint = $config['api_endpoint'];
		}
		if ( isset( $config['terminal_id'] ) ) {
			$this->terminal_id = $config['terminal_id'];
		}
		if ( isset( $config['terminal_password'] ) ) {
			$this->terminal_password = $config['terminal_password'];
		}
		if ( isset( $config['secret_key'] ) ) {
			$this->secret_key = $config['secret_key'];
		}
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Debug message.
	 * @param array  $context Additional context.
	 */
	private function log_debug( string $message, array $context = array() ): void {
		if ( $this->logger ) {
			$this->logger->debug( $message, $context );
		}
	}

	/**
	 * Get API endpoint based on request type
	 *
	 * @param string $request_type Request type.
	 * @return string
	 */
	private function get_api_endpoint( string $request_type = 'PaymentInitRequest' ): string {
		$base_url = $this->test_mode
			? 'https://ipgtest.novabanka.com/IPGWeb/servlet'
			: 'https://ipg.novabanka.com/IPGWeb/servlet';

		return trailingslashit( $base_url ) . $request_type;
	}

	/**
	 * Format amount to two decimal places
	 *
	 * @param float $amount Amount to format.
	 * @return string
	 */
	private function format_amount( float $amount ): string {
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Send API request
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @return array
	 * @throws NovaBankaIPGException When API request fails.
	 */
	private function send_api_request( string $endpoint, array $data ): array {
		$this->logger->debug(
			'Sending API request.',
			array(
				'endpoint' => $endpoint,
				'data'     => $data,
				'headers'  => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
			)
		);

		// JSON encode the request data
		$json_data = wp_json_encode( $data );
		if ( false === $json_data ) {
			throw new NovaBankaIPGException( 'Failed to encode request data as JSON' );
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers'   => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'      => $json_data,  // Send JSON encoded string
				'timeout'   => 30,
				'sslverify' => ! $this->test_mode,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new NovaBankaIPGException(
				'API request failed: ' . $response->get_error_message()
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		$this->logger->debug(
			'API response received.',
			array(
				'status_code' => $status_code,
				'headers'     => wp_remote_retrieve_headers( $response ),
				'body'        => $body,
			)
		);

		if ( $status_code !== 200 ) {
			throw new NovaBankaIPGException(
				'API request failed with status code: ' . $status_code
			);
		}

		$response_data = json_decode( $body, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new NovaBankaIPGException(
				'Failed to decode API response: ' . json_last_error_msg()
			);
		}

		return $response_data;
	}

	/**
	 * Initialize payment with IPG (Process A)
	 *
	 * @param array $payment_data Payment initialization data.
	 * @return array IPG response data.
	 * @throws NovaBankaIPGException If request fails.
	 */
	public function initialize_payment( array $payment_data ): array {
		$this->logger->debug(
			'Starting payment initialization',
			array(
				'input_data' => $this->mask_sensitive_data( $payment_data ),
			)
		);

		// Add required fields to payment data
		$payment_data = array_merge(
			$payment_data,
			array(
				'id'                 => $this->terminal_id,
				'password'           => $this->terminal_password,
				'langid'             => 'USA',
				'action'             => '1',
				'paymentPageMode'    => '0',
				'notificationFormat' => 'json',
				'cardSHA2'           => 'Y',
				'version'            => '1',
				'msgName'            => 'PaymentInitRequest',
				'payinst'            => 'VPAS',
				'recurAction'        => '',
			)
		);

		$request = $this->message_handler->generate_payment_init_request( $payment_data );

		$endpoint = $this->get_api_endpoint( 'PaymentInitRequest' );
		$response = $this->send_api_request( $endpoint, $request );

		// Append PaymentID to browserRedirectionURL
		if (isset($response['browserRedirectionURL']) && isset($response['paymentid'])) {
			$response['browserRedirectionURL'] = add_query_arg(
				'PaymentID',
				$response['paymentid'],
				$response['browserRedirectionURL']
			);
		}

		$this->logger->debug(
			'Payment initialization completed',
			array(
				'payment_id'   => $response['paymentid'] ?? null,
				'redirect_url' => $response['browserRedirectionURL'] ?? null,
			)
		);

		return $response;
	}

	/**
	 * Verify IPG notification signature (Process B)
	 *
	 * @param array $notification Notification data from IPG.
	 * @return bool Whether signature is valid.
	 */
	public function verify_signature( array $notification ): bool {
		return $this->message_handler->verify_notification_signature( $notification );
	}

	private function mask_sensitive_data(array $data): array {
		$sensitive_fields = [
			'password',
			'secret_key',
			'terminal_password',
			'msgVerifier'
		];
		
		$masked_data = $data;
		foreach ($sensitive_fields as $field) {
			if (isset($masked_data[$field])) {
				$masked_data[$field] = '***REDACTED***';
			}
		}
		
		return $masked_data;
	}

	private function make_request($endpoint, $data) {
		// Log request
		$this->logger->log_api_communication('REQUEST', $endpoint, $data);

		$response = wp_remote_post(
			$endpoint,
			array(
				'body'    => wp_json_encode($data),
				'timeout' => 45,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
			)
		);

		// Log response
		$this->logger->log_api_communication('RESPONSE', $endpoint, [
			'status_code' => wp_remote_retrieve_response_code($response),
			'body' => json_decode(wp_remote_retrieve_body($response), true)
		]);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			$this->log_debug(
				'API request failed.',
				array(
					'error'    => $error_message,
					'wp_error' => $response->get_error_codes(),
				)
			);
			throw NovaBankaIPGException::apiError(
				sprintf( 'Gateway connection failed: %s.', esc_html( $error_message ) )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$headers     = wp_remote_retrieve_headers( $response );
		$body        = wp_remote_retrieve_body( $response );

		$this->log_debug(
			'API response received.',
			array(
				'status_code' => $status_code,
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		$result = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_debug(
				'Invalid JSON response.',
				array(
					'json_error' => json_last_error_msg(),
					'raw_body'   => $body,
				)
			);
			throw NovaBankaIPGException::apiError( 'Invalid JSON response from gateway.' );
		}

		return $result;
	}
}
