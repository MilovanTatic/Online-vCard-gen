<?php
/**
 * Message Handler Implementation
 *
 * Handles message construction, verification, and processing for IPG integration.
 *
 * @package     NovaBankaIPG\Utils
 * @since       1.0.0
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;

defined( 'ABSPATH' ) || exit;

/**
 * Class MessageHandler.
 *
 * Handles message construction, verification, and processing for IPG integration.
 *
 * @package     NovaBankaIPG\Utils
 * @since       1.0.0
 */
class MessageHandler {
	/**
	 * Secret key for message verification.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Data handler instance.
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 *
	 * @param string      $secret_key   Secret key for message verification.
	 * @param DataHandler $data_handler Data handler instance.
	 * @param Logger      $logger       Logger instance.
	 */
	public function __construct( $secret_key, DataHandler $data_handler, Logger $logger ) {
		$this->secret_key   = $secret_key;
		$this->data_handler = $data_handler;
		$this->logger       = $logger;
	}

	/**
	 * Generate PaymentInit request message.
	 *
	 * @param array $data Payment data.
	 * @return array
	 * @throws NovaBankaIPGException When required fields are missing or invalid.
	 */
	public function generate_payment_init_request( array $data ): array {
		try {
			// Validate required fields.
			$this->validate_required_fields(
				$data,
				array(
					'id',
					'password',
					'amount',
					'currency',
					'trackid',
					'responseURL',
					'errorURL',
					'langid',
				)
			);

			$request = $this->prepare_payment_init_request( $data );

			$this->logger->debug( 'Generated PaymentInit request', array( 'request' => $request ) );

			return $request;

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Failed to generate PaymentInit request',
				array(
					'error' => $e->getMessage(),
					'data'  => $data,
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ), 'REQUEST_GENERATION_ERROR', esc_html( $data ) );
		}
	}

	/**
	 * Verify PaymentInit response.
	 *
	 * @param array $response Response from IPG.
	 * @return array
	 * @throws NovaBankaIPGException When response verification fails.
	 */
	public function verify_payment_init_response( array $response ): array {
		try {
			$this->validate_required_fields(
				$response,
				array(
					'msgName',
					'version',
					'msgDateTime',
					'paymentid',
					'browserRedirectionURL',
					'msgVerifier',
				)
			);

			// Calculate expected verifier.
			$expected_verifier = $this->generateMessageVerifier(
				$response['msgName'],
				$response['version'],
				$response['msgDateTime'],
				$response['paymentid'],
				$this->secret_key,
				$response['browserRedirectionURL']
			);

			if ( ! hash_equals( $expected_verifier, $response['msgVerifier'] ) ) {
				throw new NovaBankaIPGException( 'Invalid response signature' );
			}

			return $response;

		} catch ( \Exception $e ) {
			$this->logger->error(
				'PaymentInit response verification failed',
				array(
					'error'    => $e->getMessage(),
					'response' => $response,
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ), 'RESPONSE_VERIFICATION_ERROR', esc_html( $response ) );
		}
	}

	/**
	 * Generate notification response.
	 *
	 * @param array  $notification Notification data from IPG.
	 * @param string $redirect_url URL for browser redirection.
	 * @return array
	 * @throws NovaBankaIPGException When notification response generation fails.
	 */
	public function generate_notification_response( array $notification, string $redirect_url ): array {
		try {
			$this->validate_required_fields( $notification, array( 'paymentid' ) );

			$response = array(
				'msgName'               => 'PaymentNotificationResponse',
				'version'               => '1',
				'paymentID'             => $notification['paymentid'],
				'browserRedirectionURL' => $redirect_url,
			);

			// Generate message verifier.
			$response['msgVerifier'] = $this->generateMessageVerifier(
				$response['msgName'],
				$response['version'],
				$response['paymentID'],
				$this->secret_key,
				$response['browserRedirectionURL']
			);

			return $response;

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Failed to generate notification response',
				array(
					'error'        => $e->getMessage(),
					'notification' => $notification,
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ), 'NOTIFICATION_RESPONSE_ERROR', esc_html( $notification ) );
		}
	}

	/**
	 * Generate message verifier.
	 *
	 * @param mixed ...$fields Fields to include in verifier.
	 * @return string
	 */
	private function generate_message_verifier( ...$fields ): string {
		// Concatenate fields.
		$message = implode( '', array_filter( $fields ) );

		// Remove spaces.
		$message = preg_replace( '/\s+/', '', $message );

		// Generate SHA-256 hash and encode as Base64.
		return base64_encode( hash( 'sha256', $message, true ) );
	}

	/**
	 * Validate required fields.
	 *
	 * @param array $data   Data to validate.
	 * @param array $fields Required field names.
	 * @throws NovaBankaIPGException If a required field is missing.
	 */
	private function validate_required_fields( array $data, array $fields ): void {
		foreach ( $fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				throw new NovaBankaIPGException( esc_html( "Missing required field: {$field}" ) );
			}
		}
	}

	/**
	 * Validate payment instrument.
	 *
	 * @param string $instrument Payment instrument code.
	 * @return string
	 * @throws NovaBankaIPGException If the payment instrument is invalid.
	 */
	private function validate_payment_instrument( string $instrument ): string {
		$valid_instruments = array( 'CC', 'VPAS', 'IP', 'MPASS', 'MYBANK' );

		if ( ! in_array( $instrument, $valid_instruments, true ) ) {
			throw new NovaBankaIPGException( 'Invalid payment instrument' );
		}

		return $instrument;
	}

	/**
	 * Validate recurring action.
	 *
	 * @param string $action Recurring action.
	 * @return string
	 * @throws NovaBankaIPGException If the recurring action is invalid.
	 */
	private function validate_recur_action( string $action ): string {
		$valid_actions = array( 'activation', 'consumer_initiated', '' );

		if ( ! in_array( strtolower( $action ), $valid_actions, true ) ) {
			throw new NovaBankaIPGException( 'Invalid recurring action' );
		}

		return $action;
	}

	/**
	 * Add buyer information to request.
	 *
	 * @param array $request Request array to modify.
	 * @param array $data    Source data.
	 */
	private function add_buyer_information( array &$request, array $data ): void {
		$buyer_fields = array(
			'buyerFirstName'    => 50,
			'buyerLastName'     => 50,
			'buyerPhoneNumber'  => 20,
			'buyerEmailAddress' => 255,
			'buyerUserId'       => 50,
		);

		foreach ( $buyer_fields as $field => $max_length ) {
			if ( ! empty( $data[ $field ] ) ) {
				$request[ $field ] = substr( sanitize_text_field( $data[ $field ] ), 0, $max_length );
			}
		}
	}

	/**
	 * Add UDF fields to request.
	 *
	 * @param array $request Request array to modify.
	 * @param array $data    Source data.
	 */
	private function add_u_d_f_fields( array &$request, array $data ): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$field = "udf{$i}";
			if ( isset( $data[ $field ] ) ) {
				$request[ $field ] = $this->data_handler->format_udf( $data[ $field ] );
			}
		}
	}
	/**
	 * Generate Payment Query request.
	 *
	 * @param string $terminal_id Terminal ID.
	 * @param string $password    Terminal password.
	 * @param string $payment_id  Payment ID to query.
	 * @return array
	 * @throws NovaBankaIPGException If request generation fails due to invalid input or processing error.
	 */
	public function generate_payment_query_request(
		string $terminal_id,
		string $password,
		string $payment_id
	): array {
		try {
			$request = array(
				'msgName'   => 'PaymentQueryRequest',
				'version'   => '1',
				'id'        => $terminal_id,
				'password'  => $password,
				'action'    => '8', // Payment Query action code.
				'paymentid' => $payment_id,
			);

			// Generate message verifier according to spec.
			$request['msgVerifier'] = $this->generate_message_verifier(
				$request['msgName'],
				$request['version'],
				$request['id'],
				$request['password'],
				$request['action'],
				$this->secret_key,
				$request['paymentid']
			);

			$this->logger->debug( 'Generated Payment Query request', array( 'request' => $request ) );

			return $request;

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Failed to generate Payment Query request',
				array(
					'error'      => $e->getMessage(),
					'payment_id' => $payment_id,
				)
			);
			throw new NovaBankaIPGException( $e->getMessage(), 'QUERY_REQUEST_ERROR' );
		}
	}

	/**
	 * Process Payment Query response.
	 *
	 * @param array $response Response from gateway.
	 * @return array Processed response data.
	 * @throws NovaBankaIPGException If response processing fails due to invalid input or processing error.
	 */
	public function process_payment_query_response( array $response ): array {
		try {
			// Validate required fields.
			$this->validateRequiredFields(
				$response,
				array(
					'msgName',
					'version',
					'msgDateTime',
					'paymentid',
					'trackid',
					'status',
					'result',
					'amt',
					'msgVerifier',
				)
			);

			// Verify message signature.
			$message_verifier = $this->generate_message_verifier(
				$response['msgName'],
				$response['version'],
				$response['msgDateTime'],
				$response['paymentid'],
				$response['amt'],
				$response['trackid'],
				$response['udf1'] ?? '',
				$this->secret_key,
				$response['udf5'] ?? ''
			);

			if ( ! hash_equals( $message_verifier, $response['msgVerifier'] ) ) {
				throw new NovaBankaIPGException( 'Invalid query response signature' );
			}

			// Process status and result.
			return $this->parse_query_response( $response );

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Payment Query response processing failed',
				array(
					'error'    => $e->getMessage(),
					'response' => $response,
				)
			);
			throw new NovaBankaIPGException( $e->getMessage(), 'QUERY_RESPONSE_ERROR', $response );
		}
	}

	/**
	 * Parse Payment Query response.
	 *
	 * @param array $response Raw response data.
	 * @return array Processed response data.
	 */
	private function parse_query_response( array $response ): array {
		$processed = array(
			'payment_id' => $response['paymentid'],
			'track_id'   => $response['trackid'],
			'status'     => $this->getStatusDescription( $response['status'] ),
			'result'     => $response['result'],
			'amount'     => $response['amt'],
			'currency'   => $response['currencycode'],
			'timestamp'  => array(
				'init'    => $response['payinittm'] ?? null,
				'present' => $response['payprsntm'] ?? null,
				'process' => $response['payprcstm'] ?? null,
			),
		);

		// Add payment instrument if present.
		if ( ! empty( $response['payinst'] ) ) {
			$processed['payment_instrument'] = $response['payinst'];
		}

		// Add 3DS information if available.
		if ( ! empty( $response['eci'] ) ) {
			$processed['threeds'] = array(
				'eci'             => $response['eci'],
				'cavv'            => $response['cavv'] ?? null,
				'xid'             => $response['xid'] ?? null,
				'liability_shift' => $response['liability'] ?? null,
			);
		}

		// Add risk information if available.
		if ( isset( $response['riskLevel'] ) ) {
			$processed['risk'] = array(
				'level'     => $response['riskLevel'],
				'threshold' => $response['riskThreshold'] ?? null,
				'score'     => $response['riskScore'] ?? null,
				'max_score' => $response['riskMaxScore'] ?? null,
			);
		}

		// Add transaction rows if present.
		if ( ! empty( $response['rows'] ) && ! empty( $response['row'] ) ) {
			$processed['transactions'] = $this->parse_transaction_rows( $response['row'] );
		}

		return $processed;
	}

	/**
	 * Parse transaction rows from response.
	 *
	 * @param array $rows Transaction rows.
	 * @return array Processed transaction data.
	 */
	private function parse_transaction_rows( array $rows ): array {
		$transactions = array();

		foreach ( $rows as $row ) {
			$transaction = array(
				'action'         => $row['action'],
				'transaction_id' => $row['tranid'],
				'timestamp'      => $row['msgDateTime'],
				'amount'         => $row['amt'],
				'result'         => $row['result'],
				'auth_code'      => $row['auth'] ?? null,
				'card_type'      => $row['cardtype'] ?? null,
				'response_code'  => $row['responsecode'] ?? null,
				'reference'      => $row['ref'] ?? null,
			);

			// Add UDF fields if present.
			for ( $i = 1; $i <= 5; $i++ ) {
				$udf = "udf{$i}";
				if ( ! empty( $row[ $udf ] ) ) {
					$transaction['udf'][ $udf ] = $row[ $udf ];
				}
			}

			$transactions[] = $transaction;
		}

		return $transactions;
	}

	/**
	 * Get human-readable status description.
	 *
	 * @param string $status Status code from response.
	 * @return string
	 */
	private function get_status_description( string $status ): string {
		$statuses = array(
			'INITIALIZED' => 'Payment initialized but not yet displayed to customer',
			'PRESENTED'   => 'Payment page presented but process not completed',
			'PROCESSED'   => 'Payment has been processed completely',
			'TIMEOUT'     => 'Payment expired due to timeout',
		);

		return $statuses[ $status ] ?? $status;
	}

	/**
	 * Check if payment query response indicates success.
	 *
	 * @param array $response Processed response.
	 * @return bool
	 */
	public function is_query_response_successful( array $response ): bool {
		$success_results = array(
			'CAPTURED',
			'APPROVED',
		);
		return in_array( $response['result'], $success_results, true ) &&
				'PROCESSED' === $response['status'];
	}

	/**
	 * Extract transaction details from query response.
	 *
	 * @param array $response Processed response.
	 * @return array|null Transaction details or null if not found.
	 */
	public function get_transaction_details( array $response ): ?array {
		if ( empty( $response['transactions'] ) ) {
			return null;
		}

		// Find the main transaction (usually the first one).
		foreach ( $response['transactions'] as $transaction ) {
			if ( in_array( $transaction['action'], array( '1', '4' ) ) ) { // Purchase or Authorization.
				return $transaction;
			}
		}

		return $response['transactions'][0] ?? null;
	}

	/**
	 * Prepare PaymentInit request data.
	 *
	 * @param array $data Payment initialization data.
	 * @return array Prepared request data.
	 * @throws NovaBankaIPGException When data validation fails.
	 */
	private function prepare_payment_init_request( array $data ): array {
		$request = array(
			'msgName'            => 'PaymentInitRequest',
			'version'            => '1',
			'id'                 => $data['id'],
			'password'           => $data['password'],
			'action'             => '1',  // Fixed value for standard payment
			'currencycode'       => $this->data_handler->get_currency_code($data['currency']),
			'amt'                => $this->data_handler->format_amount($data['amount']),
			'trackid'            => $this->data_handler->format_track_id($data['trackid']),
			'responseURL'        => $data['responseURL'],
			'errorURL'           => $data['errorURL'],
			'langid'             => $this->data_handler->validate_language_code($data['langid']),
			'notificationFormat' => 'json',
		);

		// Only add RecurAction if it's a recurring payment
		if (isset($data['recurAction']) && !empty($data['recurAction'])) {
			$request['RecurAction'] = $this->validate_recur_action($data['recurAction']);
			if (!empty($data['recurContractId'])) {
				$request['RecurContractId'] = substr($data['recurContractId'], 0, 30);
			}
		}

		// Add buyer information if provided
		if (!empty($data['email'])) {
			$request['buyerEmailAddress'] = $data['email'];
		}

		// Add UDF fields
		if (!empty($data['udf1'])) {
			$request['udf1'] = $data['udf1'];
		}

		// Generate message verifier
		$request['msgVerifier'] = $this->generate_message_verifier(
			$request['msgName'],
			$request['version'],
			$request['id'],
			$request['password'],
			$request['amt'],
			$request['trackid'],
			$request['udf1'] ?? '',
			$this->secret_key,
			$request['udf5'] ?? ''
		);

		$this->logger->debug('Generated PaymentInit request', array('request' => $request));

		return $request;
	}
}
