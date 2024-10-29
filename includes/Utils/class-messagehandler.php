<?php
/**
 * Message Handler Class
 *
 * Handles message verification and signature generation for IPG communication.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;

/**
 * Handles message verification and signature generation.
 */
class MessageHandler {

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
	 * Constructor.
	 *
	 * @param string $terminal_id      Terminal ID.
	 * @param string $terminal_password Terminal password.
	 * @param string $secret_key       Secret key.
	 * @param Logger $logger           Logger instance.
	 */
	public function __construct(
		string $terminal_id,
		string $terminal_password,
		string $secret_key,
		Logger $logger
	) {
		$this->terminal_id       = $terminal_id;
		$this->terminal_password = $terminal_password;
		$this->secret_key        = $secret_key;
		$this->logger            = $logger;
	}

	/**
	 * Generate message verifier for IPG requests.
	 *
	 * @param array $data Data to generate verifier for.
	 * @return string Generated message verifier.
	 */
	public function generate_verifier( array $data ): string {
		$data = SharedUtilities::format_payment_data( $data );
		return $this->create_hash( $data );
	}

	/**
	 * Verify notification signature.
	 *
	 * @param array $notification_data Notification data to verify.
	 * @return bool True if signature is valid.
	 */
	public function verify_notification_signature( array $notification_data ): bool {
		if ( ! isset( $notification_data['msgVerifier'] ) ) {
			return false;
		}

		// Build verification string.
		$verifier_base = $notification_data['msgName'] .
			$notification_data['version'] .
			$notification_data['paymentid'] .
			$this->secret_key .
			( $notification_data['browserRedirectionURL'] ?? '' );

		// Generate SHA-256 hash.
		$hash = hash( 'sha256', $verifier_base );

		// Convert to binary.
		$hash_bytes = hex2bin( $hash );

		// Convert to base64.
		$expected_verifier = base64_encode( $hash_bytes );

		$is_valid = hash_equals( $expected_verifier, $notification_data['msgVerifier'] );

		$this->logger->debug(
			'Verified notification signature.',
			array(
				'payment_id' => $notification_data['paymentid'] ?? 'unknown',
				'is_valid'   => $is_valid,
			)
		);

		return $is_valid;
	}

	/**
	 * Create hash for message verification.
	 *
	 * @param array $data Data to create hash from.
	 * @return string Generated hash in base64 format.
	 */
	private function create_hash( array $data ): string {
		// Implementation from dev-message_verifier_hash_example.md.
		$verifier_base = implode(
			'',
			array(
				$data['msgName'],
				$data['version'],
				$this->terminal_id,
				$this->terminal_password,
				$data['amt'] ?? '',
				$data['trackId'] ?? '',
				$this->secret_key,
			)
		);

		return base64_encode( hex2bin( hash( 'sha256', $verifier_base ) ) );
	}

	/**
	 * Validate and sanitize request data.
	 *
	 * @param array $data Request data to validate.
	 * @return array Sanitized request data.
	 */
	public function validate_request( array $data ): array {
		$sanitized_data = array_map( 'sanitize_text_field', wp_unslash( $data ) );

		$this->logger->debug(
			'Validated request data.',
			array( 'data' => SharedUtilities::redact_sensitive_data( $sanitized_data ) )
		);

		return $sanitized_data;
	}

	/**
	 * Get and validate JSON payload from request.
	 *
	 * @return array Validated JSON data.
	 * @throws NovaBankaIPGException When JSON is invalid.
	 */
	public function get_json_payload(): array {
		$raw_post = file_get_contents( 'php://input' );
		$data     = json_decode( $raw_post, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			$this->logger->error( 'Invalid JSON payload received' );
			throw NovaBankaIPGException::invalid_response( 'Invalid JSON payload' );
		}

		return $this->validate_request( $data );
	}
}
