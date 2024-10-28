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
	 * Data Handler instance.
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
	 * Constructor.
	 *
	 * @param string      $terminal_id      Terminal ID.
	 * @param string      $terminal_password Terminal password.
	 * @param string      $secret_key       Secret key.
	 * @param DataHandler $data_handler     Data Handler instance.
	 * @param Logger      $logger           Logger instance.
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
	 * Generate message verifier for IPG requests.
	 *
	 * @param string $msg_name Message name.
	 * @param string $version  Version.
	 * @param string $id       Terminal ID.
	 * @param string $password Terminal password.
	 * @param string $amount   Transaction amount.
	 * @param string $track_id Track ID.
	 * @return string Generated message verifier.
	 */
	public function generate_message_verifier(
		string $msg_name,
		string $version,
		string $id,
		string $password,
		string $amount,
		string $track_id
	): string {
		// Build message verifier base string.
		$verifier_base = $msg_name . $version . $id . $password . $amount . $track_id . '' . $this->secret_key . '';

		// Generate SHA-256 hash.
		$hash = hash( 'sha256', $verifier_base );

		// Convert to binary.
		$hash_bytes = hex2bin( $hash );

		// Convert to base64.
		$verifier = base64_encode( $hash_bytes );

		$this->logger->debug(
			'Generated message verifier.',
			array(
				'msg_name' => $msg_name,
				'track_id' => $track_id,
				'verifier' => $verifier,
			)
		);

		return $verifier;
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
}
