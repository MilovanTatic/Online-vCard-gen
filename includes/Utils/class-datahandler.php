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
use NovaBankaIPG\Interfaces\DataHandler as DataHandlerInterface;

/**
 * DataHandler Class
 *
 * Handles data formatting and validation for the NovaBanka IPG plugin.
 * Implements DataHandlerInterface for standardized data operations.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class DataHandler implements DataHandlerInterface {
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
	 * @param float $amount Amount to format.
	 * @return string
	 * @throws NovaBankaIPGException If the amount is not numeric or exceeds the maximum allowed value.
	 */
	public function format_amount( float $amount ): string {
		// Remove any existing formatting.
		$amount_str = str_replace( array( ',', ' ' ), '', (string) $amount );

		if ( ! is_numeric( $amount_str ) ) {
			throw new NovaBankaIPGException( 'Invalid amount format.' );
		}

		$amount_float = (float) $amount_str;

		if ( $amount_float > 9999999999.99 ) {
			throw new NovaBankaIPGException( 'Amount exceeds maximum allowed value.' );
		}

		return number_format( $amount_float, 2, '.', '' );
	}

	/**
	 * Format a phone number to the expected format for API communication.
	 *
	 * @param string $phone The phone number to format.
	 * @return string|null
	 */
	public function format_phone( string $phone ): ?string {
		if ( empty( $phone ) ) {
			return null;
		}

		// Remove everything except numbers and +.
		$formatted = preg_replace( '/[^0-9+]/', '', $phone );

		// Ensure + is only at the start.
		$formatted = preg_replace( '/(?!^)\+/', '', $formatted );

		// Truncate to max length.
		return substr( $formatted, 0, self::FIELD_LENGTHS['phone'] );
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

	/**
	 * Format an item amount by multiplying the amount by quantity and formatting the total.
	 *
	 * @param float $amount   The base amount to format.
	 * @param int   $quantity The quantity to multiply by (default: 1).
	 * @return string The formatted total amount.
	 */
	public function format_item_amount( float $amount, int $quantity = 1 ): string {
		// Calculate total and format it.
		$total = $amount * $quantity;
		return $this->format_amount( $total );
	}

	/**
	 * Validates a language code to ensure it matches IPG specifications.
	 *
	 * @param string $lang_code The language code to validate.
	 * @return string The validated language code or 'EN' if invalid.
	 */
	public function validate_language_code( string $lang_code ): string {
		// Ensure language code is two or three letters and uppercase.
		$lang_code = strtoupper( $lang_code );
		if ( preg_match( '/^[A-Z]{2,3}$/', $lang_code ) ) {
			return $lang_code;
		}
		// Return default if invalid.
		return 'EN';
	}
}
