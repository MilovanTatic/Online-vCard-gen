<?php
/**
 * DataHandler Utility Class
 *
 * This class is responsible for handling various data formatting and validation processes,
 * such as formatting payment amounts, phone numbers, item quantities, and validating language codes.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;

class DataHandler {
	/**
	 * Currency codes mapping as per IPG specs.
	 *
	 * @var array
	 */
	protected const CURRENCY_CODES = array(
		'EUR' => '978',
		'USD' => '840',
		'GBP' => '826',
		'BAM' => '977',
	);

	/**
	 * Maximum field lengths as per IPG specs.
	 *
	 * @var array
	 */
	protected const FIELD_LENGTHS = array(
		'phone'    => 20,
		'email'    => 255,
		'amount'   => 10,  // Plus 2 decimals.
		'name'     => 50,
		'address1' => 100,
		'address2' => 100,
		'address3' => 40,
		'city'     => 40,
		'zip'      => 20,
	);

	/**
	 * Format the payment amount to the required decimal places.
	 *
	 * @param float|string $amount Amount to format.
	 * @return string
	 * @throws NovaBankaIPGException If the amount is not numeric or exceeds the maximum allowed value.
	 */
	public function format_amount( $amount ) {
		// Remove any existing formatting.
		$amount = str_replace( array( ',', ' ' ), '', (string) $amount );

		if ( ! is_numeric( $amount ) ) {
			throw NovaBankaIPGException::invalidRequest( 'Invalid amount format.' );
		}

		$amount = (float) $amount;

		// Check maximum value.
		if ( $amount > 9999999999.99 ) {
			throw NovaBankaIPGException::invalidRequest( 'Amount exceeds maximum allowed value.' );
		}

		// Format with exactly 2 decimal places.
		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Format a phone number to the expected format for API communication.
	 *
	 * @param string $phone_number The phone number to format.
	 * @return string|null
	 */
	public static function format_phone( $phone_number ) {
		if ( empty( $phone_number ) ) {
			return null;
		}

		// Remove everything except numbers and +.
		$phone_number = preg_replace( '/[^0-9+]/', '', $phone_number );

		// Ensure + is only at the start.
		$phone_number = preg_replace( '/(?!^)\+/', '', $phone_number );

		// Truncate to max length defined in FIELD_LENGTHS.
		return substr( $phone_number, 0, self::FIELD_LENGTHS['phone'] );
	}

	/**
	 * Format email according to IPG specifications.
	 *
	 * @param string $email Email address.
	 * @return string|null
	 */
	public function format_email( $email ) {
		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return null;
		}

		// Truncate to max length defined in FIELD_LENGTHS.
		return substr( $email, 0, self::FIELD_LENGTHS['email'] );
	}

	/**
	 * Validate the language code to ensure it meets expected standards.
	 *
	 * @param string $language_code The language code to validate.
	 * @return bool True if the language code is valid, false otherwise.
	 */
	public static function validate_language_code( $language_code ) {
		// Ensure language code is two or three letters (e.g., 'EN', 'FR', 'ESP').
		return preg_match( '/^[a-zA-Z]{2,3}$/', $language_code ) === 1;
	}

	/**
	 * Format the item quantity to an integer value.
	 *
	 * @param float $quantity The quantity to format.
	 * @return int The formatted item quantity.
	 */
	public static function format_quantity( $quantity ) {
		return (int) $quantity;
	}

	/**
	 * Get currency code based on the currency name.
	 *
	 * @param string $currency Currency name (e.g., 'EUR', 'USD').
	 * @return string|null Currency code or null if not found.
	 */
	public function get_currency_code( $currency ) {
		return self::CURRENCY_CODES[ $currency ] ?? null;
	}
}
