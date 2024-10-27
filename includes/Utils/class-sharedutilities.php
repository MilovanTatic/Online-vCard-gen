<?php
/**
 * Utility Class for Shared Functions
 *
 * This class is responsible for housing shared utility functions used across various components.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use NovaBankaIPG\Interfaces\SharedUtilitiesInterface;
use NovaBankaIPG\Interfaces\LoggerInterface;
use NovaBankaIPG\Interfaces\DataHandlerInterface;
use NovaBankaIPG\Config\Config;

/**
 * SharedUtilities Class
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class SharedUtilities implements SharedUtilitiesInterface {

	/**
	 * Get the API endpoint URL based on test mode setting.
	 *
	 * @param string $path Path to append to the API endpoint URL.
	 * @return string The API endpoint URL with the appended path.
	 */
	public static function get_api_endpoint( string $path ): string {
		$base_url = Config::is_test_mode()
			? Config::get_setting( 'test_api_endpoint' )
			: Config::get_setting( 'live_api_endpoint' );

		return rtrim( $base_url, '/' ) . '/' . ltrim( $path, '/' );
	}

	/**
	 * Format amount according to IPG requirements.
	 *
	 * @param float|string $amount Amount to format.
	 * @return string Formatted amount.
	 * @throws NovaBankaIPGException If amount is invalid.
	 */
	public static function format_amount( $amount ): string {
		if ( ! is_numeric( $amount ) ) {
			throw new NovaBankaIPGException( 'Invalid amount format' );
		}
		return number_format( (float) $amount, 2, '.', '' );
	}

	/**
	 * Generate message verifier.
	 *
	 * @param mixed ...$fields Fields to include in verifier.
	 * @return string
	 */
	public static function generate_message_verifier( ...$fields ): string {
		// Direct concatenation without spaces between fields.
		$message = implode( '', $fields );

		Logger::debug(
			'Message verifier generation:',
			array(
				'initial_string' => $message,
				'initial_hex'    => bin2hex( $message ),
			)
		);

		// Remove all spaces.
		$message = preg_replace( '/\s+/', '', $message );

		Logger::debug(
			'After space removal:',
			array(
				'processed_string' => $message,
				'processed_hex'    => bin2hex( $message ),
			)
		);

		// Get raw hash bytes.
		$hash_bytes = hash( 'sha256', $message, true );

		Logger::debug(
			'Hash bytes:',
			array(
				'hex' => strtoupper( bin2hex( $hash_bytes ) ),
			)
		);

		// Base64 encode.
		$verifier = base64_encode( $hash_bytes );

		Logger::debug(
			'Final verifier:',
			array(
				'verifier' => $verifier,
			)
		);

		return $verifier;
	}

	/**
	 * Validate required fields.
	 *
	 * @param array $data Data to validate.
	 * @param array $fields Required field names.
	 * @throws NovaBankaIPGException If a required field is missing.
	 */
	public static function validate_required_fields( array $data, array $fields ): void {
		foreach ( $fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				throw new NovaBankaIPGException( esc_html( "Missing required field: {$field}" ) );
			}
		}
	}

	/**
	 * Add buyer information to request.
	 *
	 * @param array $request Request array to modify.
	 * @param array $data Source data.
	 */
	public static function add_buyer_information( array &$request, array $data ): void {
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
	 * @param array $data Source data.
	 */
	public static function add_udf_fields( array &$request, array $data ): void {
		for ( $i = 1; $i <= 5; $i++ ) {
			$field = "udf{$i}";
			if ( isset( $data[ $field ] ) ) {
				$request[ $field ] = DataHandler::format_udf( $data[ $field ] );
			}
		}
	}

	/**
	 * Parse transaction rows from response.
	 *
	 * @param array $rows Transaction rows.
	 * @return array Processed transaction data.
	 */
	public static function parse_transaction_rows( array $rows ): array {
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
	public static function get_status_description( string $status ): string {
		$statuses = array(
			'INITIALIZED' => 'Payment initialized but not yet displayed to customer',
			'PRESENTED'   => 'Payment page presented but process not completed',
			'PROCESSED'   => 'Payment has been processed completely',
			'TIMEOUT'     => 'Payment expired due to timeout',
		);

		return $statuses[ $status ] ?? $status;
	}
}
