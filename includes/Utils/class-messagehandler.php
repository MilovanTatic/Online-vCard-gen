<?php
/**
 * Message Handler Class
 *
 * Handles message construction, verification, and processing for IPG integration.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use Exception;

/**
 * Class MessageHandler
 *
 * Handles message construction, verification, and processing for IPG integration.
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
	 * Constructor.
	 *
	 * @param string      $terminal_id       Terminal ID.
	 * @param string      $terminal_password Terminal password.
	 * @param string      $secret_key        Secret key for message verification.
	 * @param DataHandler $data_handler      Data handler instance.
	 * @param Logger      $logger            Logger instance.
	 */
	public function __construct(
		string $terminal_id,
		string $terminal_password,
		string $secret_key,
		DataHandler $data_handler,
		Logger $logger
	) {
		$this->terminal_id       = $terminal_id;
		$this->terminal_password = $terminal_password;
		$this->secret_key        = $secret_key;
		$this->data_handler      = $data_handler;
		$this->logger            = $logger;
	}

	/**
	 * Generate PaymentInit request message.
	 *
	 * @param array $data Payment data.
	 * @return array Prepared request message.
	 * @throws NovaBankaIPGException When message generation fails.
	 */
	public function generate_payment_init_request( array $data ): array {
		try {
			// Allow plugins to modify request data.
			$data = apply_filters( 'novabankaipg_payment_init_data', $data );

			// Validate required fields.
			$required_fields = array(
				'id',
				'password',
				'amount',
				'currency',
				'trackid',
				'responseURL',
				'errorURL',
				'langid',
			);

			foreach ( $required_fields as $field ) {
				if ( empty( $data[ $field ] ) ) {
					throw new NovaBankaIPGException(
						sprintf(
							/* translators: %s: field name */
							esc_html__( 'Required field missing: %s', 'novabanka-ipg-gateway' ),
							$field
						)
					);
				}
			}

			$request = $this->prepare_payment_init_request( $data );

			$this->logger->debug( 'Generated PaymentInit request.', array( 'request' => $request ) );

			return $request;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to generate PaymentInit request.',
				array(
					'error' => esc_html( $e->getMessage() ),
					'data'  => wp_json_encode( $data ),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Generate notification response.
	 *
	 * @param array  $notification Notification data from IPG.
	 * @param string $redirect_url URL for browser redirection.
	 * @return array Response data.
	 * @throws NovaBankaIPGException When response generation fails.
	 */
	public function generate_notification_response( array $notification, string $redirect_url ): array {
		try {
			if ( empty( $notification['paymentid'] ) ) {
				throw new NovaBankaIPGException( esc_html__( 'Payment ID missing in notification.', 'novabanka-ipg-gateway' ) );
			}

			$response = array(
				'msgName'               => 'PaymentNotificationResponse',
				'version'               => '1',
				'paymentID'             => $notification['paymentid'],
				'browserRedirectionURL' => $redirect_url,
			);

			// Generate message verifier.
			$verifier_fields = array(
				$response['msgName'],
				$response['version'],
				$response['paymentID'],
				$this->secret_key,
				$response['browserRedirectionURL'],
			);

			$response['msgVerifier'] = SharedUtilities::generate_message_verifier( ...$verifier_fields );

			// Allow plugins to modify response.
			return apply_filters( 'novabankaipg_notification_response', $response, $notification );

		} catch ( Exception $e ) {
			$this->logger->error(
				'Failed to generate notification response.',
				array(
					'error'        => esc_html( $e->getMessage() ),
					'notification' => wp_json_encode( $notification ),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Prepare PaymentInit request data.
	 *
	 * @param array $data Payment initialization data.
	 * @return array Prepared request data.
	 * @throws NovaBankaIPGException When data preparation fails.
	 */
	private function prepare_payment_init_request( array $data ): array {
		try {
			$this->logger->debug( 'Preparing payment init request.', array( 'raw_data' => $data ) );

			// Prepare base request.
			$request = array(
				'msgName'            => 'PaymentInitRequest',
				'version'            => '1',
				'id'                 => $data['id'],
				'password'           => $data['password'],
				'action'             => '1',
				'currencycode'       => $this->data_handler->get_currency_code( $data['currency'] ),
				'amt'                => $this->data_handler->format_amount( $data['amount'] ),
				'trackid'            => (string) $data['trackid'],
				'responseURL'        => $data['responseURL'],
				'errorURL'           => $data['errorURL'],
				'langid'             => $data['langid'],
				'notificationFormat' => 'json',
				'payinst'            => 'VPAS',
				'recurAction'        => '',
			);

			// Add optional fields.
			$optional_fields = array( 'email', 'udf1', 'udf2', 'udf3' );
			foreach ( $optional_fields as $field ) {
				if ( ! empty( $data[ $field ] ) ) {
					$request[ $field ] = $data[ $field ];
				}
			}

			// Generate message verifier.
			$verifier_fields = array(
				$request['msgName'],
				$request['version'],
				$request['id'],
				$request['password'],
				$request['amt'],
				$request['trackid'],
				$data['udf1'] ?? '',
				$this->secret_key,
				$data['udf5'] ?? '',
			);

			$request['msgVerifier'] = SharedUtilities::generate_message_verifier( ...$verifier_fields );

			return $request;

		} catch ( Exception $e ) {
			throw new NovaBankaIPGException(
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'Failed to prepare payment request: %s', 'novabanka-ipg-gateway' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Generate message verifier.
	 *
	 * @param array $fields Fields to verify.
	 * @return string Message verifier.
	 */
	public function generate_verifier( array $fields ): string {
		return base64_encode(
			hash( 'sha256', implode( '', $fields ), true )
		);
	}

	/**
	 * Verify message signature.
	 *
	 * @param array  $data      Message data.
	 * @param string $signature Message signature to verify.
	 * @return bool True if signature is valid.
	 */
	public function verify_signature( array $data, string $signature ): bool {
		$verifier = $this->generate_verifier( $data );
		return hash_equals( $verifier, $signature );
	}
}
