<?php
/**
 * Data Handler Implementation
 *
 * @package     NovaBankaIPG\Utils
 * @since       1.0.0
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;

defined( 'ABSPATH' ) || exit;

/**
 * DataHandler Class
 *
 * Handles data formatting and validation according to IPG specifications.
 */
class DataHandler {
	/**
	 * Currency codes mapping as per IPG specs
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
	 * Maximum field lengths as per IPG specs
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
	 * Format amount according to IPG specifications
	 * Format: NNNNNNNNNN.NN (max value 9999999999.99)
	 *
	 * @param float|string $amount Amount to format.
	 * @return string
	 * @throws NovaBankaIPGException If the amount is not numeric or exceeds the maximum allowed value.
	 */
	public function format_amount( $amount ) {
		// Remove any existing formatting.
		$amount = str_replace( array( ',', ' ' ), '', (string) $amount );

		if ( ! is_numeric( $amount ) ) {
			throw NovaBankaIPGException::invalidRequest( 'Invalid amount format' );
		}

		$amount = (float) $amount;

		// Check maximum value.
		if ( $amount > 9999999999.99 ) {
			throw NovaBankaIPGException::invalidRequest( 'Amount exceeds maximum allowed value' );
		}

		// Format with exactly 2 decimal places.
		return number_format( $amount, 2, '.', '' );
	}

	/**
	 * Format phone number according to IPG specifications
	 * Max length: 20, only numbers and + allowed
	 *
	 * @param string $phone Phone number.
	 * @return string|null
	 */
	public function format_phone( $phone ) {
		if ( empty( $phone ) ) {
			return null;
		}

		// Remove everything except numbers and +.
		$phone = preg_replace( '/[^0-9+]/', '', $phone );

		// Ensure + is only at the start.
		$phone = preg_replace( '/(?!^)\+/', '', $phone );

		return substr( $phone, 0, self::FIELD_LENGTHS['phone'] );
	}

	/**
	 * Format email according to IPG specifications
	 *
	 * @param string $email Email address.
	 * @return string|null
	 */
	public function format_email( $email ) {
		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return null;
		}

		return substr( $email, 0, self::FIELD_LENGTHS['email'] );
	}

	/**
	 * Get numeric currency code for IPG
	 *
	 * @param string $currency Currency code (ISO 4217).
	 * @return string
	 * @throws NovaBankaIPGException If the currency is unsupported.
	 */
	public function get_currency_code( $currency ) {
		$currency = strtoupper( $currency );

		if ( ! isset( self::CURRENCY_CODES[ $currency ] ) ) {
			throw NovaBankaIPGException::invalidRequest( esc_html( "Unsupported currency: {$currency}" ) );
		}

		return self::CURRENCY_CODES[ $currency ];
	}

	/**
	 * Validate and format track ID.
	 * Must be unique per transaction, max 255 chars.
	 *
	 * @param string $track_id Track ID.
	 * @return string
	 * @throws NovaBankaIPGException If the track ID is invalid.
	 */
	public function format_track_id( $track_id ) {
		$track_id = sanitize_text_field( $track_id );

		if ( empty( $track_id ) ) {
			throw NovaBankaIPGException::invalidRequest( 'Track ID is required' );
		}

		if ( strlen( $track_id ) > 255 ) {
			throw NovaBankaIPGException::invalidRequest( 'Track ID exceeds maximum length of 255 characters' );
		}

		return $track_id;
	}

	/**
	 * Format UDF (User Defined Field).
	 * Optional fields, max 255 chars each.
	 *
	 * @param string $value UDF value.
	 * @return string|null
	 */
	public function format_udf( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		return substr( sanitize_text_field( $value ), 0, 255 );
	}

	/**
	 * Format address data according to IPG specifications.
	 *
	 * @param array $address Address data.
	 * @return array
	 */
	public function format_address( $address ) {
		$formatted = array();

		if ( ! empty( $address['country'] ) ) {
			$formatted['country'] = strtoupper( substr( $address['country'], 0, 3 ) );
		}

		if ( ! empty( $address['city'] ) ) {
			$formatted['city'] = substr( sanitize_text_field( $address['city'] ), 0, self::FIELD_LENGTHS['city'] );
		}

		if ( ! empty( $address['zip'] ) ) {
			$formatted['zip'] = substr( sanitize_text_field( $address['zip'] ), 0, self::FIELD_LENGTHS['zip'] );
		}

		if ( ! empty( $address['addrLine1'] ) ) {
			$formatted['addrLine1'] = substr( sanitize_text_field( $address['addrLine1'] ), 0, self::FIELD_LENGTHS['address1'] );
		}

		if ( ! empty( $address['addrLine2'] ) ) {
			$formatted['addrLine2'] = substr( sanitize_text_field( $address['addrLine2'] ), 0, self::FIELD_LENGTHS['address2'] );
		}

		if ( ! empty( $address['addrLine3'] ) ) {
			$formatted['addrLine3'] = substr( sanitize_text_field( $address['addrLine3'] ), 0, self::FIELD_LENGTHS['address3'] );
		}

		return array_filter( $formatted );
	}

	/**
	 * Format cart item amount.
	 *
	 * @param float $amount   Amount to format.
	 * @param int   $quantity Item quantity.
	 * @return string
	 * @throws NovaBankaIPGException If the amount is not numeric or exceeds the maximum allowed value.
	 */
	public function format_item_amount( $amount, $quantity = 1 ) {
		$total = $amount * $quantity;
		return $this->format_amount( $total );
	}

	/**
	 * Validate language code
	 * Supported codes: ITA, USA, FRA, DEU, ESP, SLO, SRB, POR, RUS
	 *
	 * @param string $lang_code Language code.
	 * @return string
	 * @throws NovaBankaIPGException If the language code is unsupported.
	 */
	public function validate_language_code( $lang_code ) {
		$supported_langs = array( 'ITA', 'USA', 'FRA', 'DEU', 'ESP', 'SLO', 'SRB', 'POR', 'RUS' );

		$lang_code = strtoupper( $lang_code );

		if ( ! in_array( $lang_code, $supported_langs, true ) ) {
			throw NovaBankaIPGException::invalidRequest( 'Unsupported language code' );
		}

		return $lang_code;
	}

	/**
	 * Sanitize payment description.
	 * Max 255 chars for recurring payment description.
	 *
	 * @param string $description Payment description.
	 * @return string|null
	 */
	public function format_payment_description( $description ) {
		if ( empty( $description ) ) {
			return null;
		}

		return substr( sanitize_text_field( $description ), 0, 255 );
	}
}
