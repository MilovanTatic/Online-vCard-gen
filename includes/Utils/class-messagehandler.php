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
use NovaBankaIPG\Utils\SharedUtilities;

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
	 * Constructor
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
	 * @return array
	 * @throws NovaBankaIPGException When required fields are missing or invalid.
	 */
	public function generate_payment_init_request( array $data ): array {
		try {
			// Validate required fields.
			SharedUtilities::validate_required_fields(
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

			$this->logger->debug( 'Generated PaymentInit request.', array( 'request' => $request ) );

			return $request;
		} catch ( \Exception $e ) {
			$this->logger->error(
				'Failed to generate PaymentInit request.',
				array(
					'error' => esc_html( $e->getMessage() ),
					'data'  => esc_html( wp_json_encode( $data ) ),
				)
			);
			throw new NovaBankaIPGException(
				esc_html( $e->getMessage() ),
				'REQUEST_GENERATION_ERROR',
				esc_html( wp_json_encode( $data ) )
			);
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
			SharedUtilities::validate_required_fields( $notification, array( 'paymentid' ) );

			$response = array(
				'msgName'               => 'PaymentNotificationResponse',
				'version'               => '1',
				'paymentID'             => $notification['paymentid'],
				'browserRedirectionURL' => $redirect_url,
			);

			// Generate message verifier.
			$response['msgVerifier'] = SharedUtilities::generate_message_verifier(
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
	 * Prepare PaymentInit request data.
	 *
	 * @param array $data Payment initialization data.
	 * @return array Prepared request data.
	 * @throws NovaBankaIPGException When data validation fails.
	 */
	private function prepare_payment_init_request( array $data ): array {
		$this->logger->debug(
			'Starting payment init request preparation',
			array(
				'raw_input_data' => $data,
			)
		);

		// Store raw values for message verification
		$raw_values = array(
			'msgName'  => 'PaymentInitRequest',
			'version'  => '1',
			'id'       => $data['id'],
			'password' => $data['password'],
			'amt'      => $this->data_handler->format_amount( $data['amount'] ),
			'trackid'  => (string) $data['trackid'],
			'udf1'     => $data['udf1'] ?? '',
			'udf5'     => $data['udf5'] ?? '',
		);

		$this->logger->debug(
			'Raw values prepared for verification',
			array(
				'raw_values' => $raw_values,
			)
		);

		// Prepare request
		$request = array(
			'msgName'            => $raw_values['msgName'],
			'version'            => $raw_values['version'],
			'id'                 => $raw_values['id'],
			'password'           => $raw_values['password'],
			'action'             => '1',
			'currencycode'       => $this->data_handler->get_currency_code( $data['currency'] ),
			'amt'                => $raw_values['amt'],
			'trackid'            => $raw_values['trackid'],
			'responseURL'        => $data['responseURL'],
			'errorURL'           => $data['errorURL'],
			'langid'             => $data['langid'],
			'notificationFormat' => 'json',
			'payinst'            => 'VPAS',
			'recurAction'        => '',
		);

		$this->logger->debug(
			'Base request prepared',
			array(
				'request' => $request,
			)
		);

		// Add optional fields.
		if ( ! empty( $data['email'] ) ) {
			$request['buyerEmailAddress'] = $data['email'];
		}

		// Add UDF fields.
		foreach ( array( 'udf1', 'udf2', 'udf3' ) as $udf ) {
			if ( ! empty( $data[ $udf ] ) ) {
				$request[ $udf ] = $data[ $udf ];
			}
		}

		// Generate message verifier using raw values.
		$verifier_fields = array(
			$raw_values['msgName'],
			$raw_values['version'],
			$raw_values['id'],
			$raw_values['password'],
			$raw_values['amt'],
			$raw_values['trackid'],
			$raw_values['udf1'],
			$this->secret_key,
			$raw_values['udf5'],
		);

		$request['msgVerifier'] = SharedUtilities::generate_message_verifier( ...$verifier_fields );

		$this->logger->debug(
			'Final request prepared',
			array(
				'final_request' => $request,
			)
		);

		return $request;
	}
}
