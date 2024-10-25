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
	}

	/**
	 * Send payment initialization request
	 *
	 * @param array $data Payment initialization data.
	 * @return array Response data from the payment initialization request.
	 * @throws NovaBankaIPGException When the payment initialization fails.
	 */
	public function send_payment_init( array $data ): array {
		try {
			$payment_init = $this->prepare_payment_init_request( $data );

			$this->log_debug( 'Sending PaymentInit request', array( 'request' => $payment_init ) );

			$response = $this->send_request( $payment_init );

			$this->log_debug( 'PaymentInit response received', array( 'response' => $response ) );

			if ( ! isset( $response['type'] ) || 'valid' !== $response['type'] ) {
				throw NovaBankaIPGException::apiError(
					isset( $response['errorDesc'] ) ? $response['errorDesc'] : 'Invalid gateway response',
					$response
				);
			}

			// Verify response signature.
			if ( ! $this->verify_signature( $response, $response['msgVerifier'] ) ) {
				throw NovaBankaIPGException::invalidSignature( 'Invalid PaymentInit response signature' );
			}

			return $response;

		} catch ( \Exception $e ) {
			$message = $e->getMessage();
			throw new NovaBankaIPGException( $message, 'API_ERROR', $data, $e );
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
			$this->log_debug( 'Processing payment notification', array( 'notification' => $notification ) );

			// Verify notification signature.
			if ( ! $this->verify_signature( $notification, $notification['msgVerifier'] ) ) {
				throw NovaBankaIPGException::invalidSignature( 'Invalid notification signature' );
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

			$this->log_debug( 'Notification response prepared', array( 'response' => $response ) );

			return $response;

		} catch ( \Exception $e ) {
			$message = $e->getMessage();
			throw new NovaBankaIPGException( $message, 'API_ERROR', $notification, $e );
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

			$this->log_debug( 'Sending refund request', array( 'request' => $refund_request ) );

			$response = $this->send_request( $refund_request );

			$this->log_debug( 'Refund response received', array( 'response' => $response ) );

			if ( 'valid' !== $response['type'] ) {
				throw NovaBankaIPGException::apiError(
					$response['errorDesc'] ?? 'Invalid refund response',
					$response
				);
			}

			// Verify response signature.
			if ( ! $this->verify_signature( $response, $response['msgVerifier'] ) ) {
				throw NovaBankaIPGException::invalidSignature( 'Invalid refund response signature' );
			}

			return $response;
		} catch ( \Exception $e ) {
			$message = $e->getMessage();
			throw new NovaBankaIPGException( $message, 'REFUND_FAILED', $data, $e );
		}
	}

	/**
	 * Send request to gateway
	 *
	 * @param array $data Request data.
	 * @return array
	 * @throws NovaBankaIPGException If the request to the gateway fails or the response is invalid.
	 */
	private function send_request( array $data ): array {
		// Get endpoint based on msgName from request data
		$endpoint = $this->get_api_endpoint( $data['msgName'] ?? 'PaymentInitRequest' );

		$this->log_debug(
			'Sending API request',
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
				'API request failed',
				array(
					'error'    => $error_message,
					'wp_error' => $response->get_error_codes(),
				)
			);
			throw NovaBankaIPGException::apiError(
				'Gateway connection failed: ' . $error_message
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$headers     = wp_remote_retrieve_headers( $response );
		$body        = wp_remote_retrieve_body( $response );

		$this->log_debug(
			'API response received',
			array(
				'status_code' => $status_code,
				'headers'     => $headers,
				'body'        => $body,
			)
		);

		$result = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_debug(
				'Invalid JSON response',
				array(
					'json_error' => json_last_error_msg(),
					'raw_body'   => $body,
				)
			);
			throw NovaBankaIPGException::apiError( 'Invalid JSON response from gateway' );
		}

		return $result;
	}

	/**
	 * Prepare payment initialization request
	 *
	 * @param array $data Request data.
	 * @return array
	 */
	private function prepare_payment_init_request( array $data ): array {
		// Base request
		$request = array(
			'msgName'            => 'PaymentInitRequest',
			'version'            => '1',
			'id'                 => $this->terminal_id,
			'password'           => $this->terminal_password,
			'action'             => $data['action'] ?? '1',
			'currencycode'       => $data['currency'],
			'amt'                => $this->data_handler->format_amount( $data['amount'] ),
			'trackid'            => $data['order_id'],
			'responseURL'        => $data['response_url'],
			'errorURL'           => $data['error_url'],
			'langid'             => $data['language'] ?? 'USA',
			'udf1'               => $data['udf1'] ?? '',
			'buyerEmailAddress'  => $data['email'] ?? '',
			'notificationFormat' => 'json',
			'payinst'           => 'VPAS',  // Always use 3DS
			'RecurAction'       => '',      // Default to normal e-commerce
		);

		// Add 3DS data if available
		if ( isset( $data['threeds_data'] ) ) {
			$request = array_merge( $request, $data['threeds_data'] );
		}

		// Add message verifier
		$request['msgVerifier'] = $this->generate_message_verifier( array(
			$request['msgName'],
			$request['version'],
			$request['id'],
			$request['password'],
			$request['amt'],
			$request['trackid'],
			$request['udf1'],
			$this->secret_key,
			isset( $request['udf5'] ) ? $request['udf5'] : ''
		) );

		$this->log_debug( 'Payment request prepared', array( 'request' => $request ) );
		
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
	 * @param array  $data      Data to verify.
	 * @param string $signature Signature to check against.
	 * @return bool
	 */
	public function verify_signature( array $data, string $signature ): bool {
		// Implementation follows IPG documentation for specific message types.
		$calculated = $this->generate_message_verifier( $this->get_signature_fields( $data ) );
		return hash_equals( $signature, $calculated );
	}

	/**
	 * Get fields for signature generation based on message type
	 *
	 * @param array $data Message data.
	 * @return array
	 */
	private function get_signature_fields( array $data ): array {
		$fields = array();

		switch ( $data['msgName'] ) {
			case 'PaymentInitResponse':
				$fields = array(
					$data['msgName'],
					$data['version'],
					$data['msgDateTime'],
					$data['paymentid'],
					$this->secret_key,
					$data['browserRedirectionURL'],
				);
				break;

			case 'PaymentNotificationRequest':
				$fields = array(
					$data['msgName'],
					$data['version'],
					$data['msgDateTime'],
					$data['paymentid'],
					$data['tranid'],
					$data['amt'],
					$data['trackid'],
					$data['udf1'] ?? '',
					$this->secret_key,
					$data['udf5'] ?? '',
				);
				break;

			// Add other message types as needed.
		}

		return $fields;
	}

	/**
	 * Generate message verifier hash
	 *
	 * @param array $fields Fields to include in hash.
	 * @return string
	 */
	public function generate_message_verifier( array $fields ): string {
		$message = implode( '', array_filter( $fields ) );
		$message = preg_replace( '/\s+/', '', $message );
		return base64_encode( hash( 'sha256', $message, true ) );
	}

	/**
	 * Log an error message
	 *
	 * @param string $error_message The error message to log.
	 * @param array  $error_context Additional context for the error.
	 */
	private function log_error_message( string $error_message, array $error_context = array() ): void {
		$this->logger->error( $error_message, $error_context );
	}

	/**
	 * Verify the notification data
	 *
	 * @param array $notification Payment notification data.
	 * @return bool
	 */
	public function verify_notification( array $notification ): bool {
		// Verify the notification data.
		return $this->verify_signature( $notification, $notification['msgVerifier'] );
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
			? 'https://ipgtest.novabanka.com'
			: 'https://ipg.novabanka.com';

		return trailingslashit( $base_url ) . 'IPGWeb/servlet/' . $request_type;
	}
}
