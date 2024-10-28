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

/**
 * DataHandler Class
 *
 * Handles data formatting and validation for the NovaBanka IPG plugin.
 */
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
	 * Required fields for API requests.
	 *
	 * @var array
	 */
	protected const REQUIRED_FIELDS = array(
		'amount',
		'currency',
		'order_id',
	);

	/**
	 * Format the payment amount to the required decimal places.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted amount.
	 * @throws NovaBankaIPGException If amount is invalid or exceeds maximum.
	 */
	public function format_amount( float $amount ): string {
		// Allow plugins to modify amount before formatting.
		$amount = apply_filters( 'novabankaipg_before_format_amount', $amount );

		// Remove any existing formatting.
		$amount_str = str_replace( array( ',', ' ' ), '', (string) $amount );

		if ( ! is_numeric( $amount_str ) ) {
			throw new NovaBankaIPGException(
				esc_html__( 'Invalid amount format.', 'novabanka-ipg-gateway' )
			);
		}

		$amount_float = (float) $amount_str;

		if ( $amount_float > 9999999999.99 ) {
			throw new NovaBankaIPGException(
				esc_html__( 'Amount exceeds maximum allowed value.', 'novabanka-ipg-gateway' )
			);
		}

		$formatted_amount = number_format( $amount_float, 2, '.', '' );

		return (string) apply_filters( 'novabankaipg_formatted_amount', $formatted_amount, $amount );
	}

	/**
	 * Format a phone number to the expected format for API communication.
	 *
	 * @param string $phone The phone number to format.
	 * @return string|null Formatted phone number or null if empty.
	 */
	public function format_phone( string $phone ): ?string {
		if ( empty( $phone ) ) {
			return null;
		}

		// Allow plugins to modify phone before formatting.
		$phone = apply_filters( 'novabankaipg_before_format_phone', $phone );

		// Remove everything except numbers and +.
		$formatted = preg_replace( '/[^0-9+]/', '', $phone );

		// Ensure + is only at the start.
		$formatted = preg_replace( '/(?!^)\+/', '', $formatted );

		// Truncate to max length.
		$formatted = substr( $formatted, 0, self::FIELD_LENGTHS['phone'] );

		return apply_filters( 'novabankaipg_formatted_phone', $formatted, $phone );
	}

	/**
	 * Format email according to IPG specifications.
	 *
	 * @param string $email Email address to format.
	 * @return string|null Formatted email or null if invalid.
	 */
	public function format_email( string $email ): ?string {
		// Allow plugins to modify email before formatting.
		$email = apply_filters( 'novabankaipg_before_format_email', $email );

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return null;
		}

		$formatted = substr( $email, 0, self::FIELD_LENGTHS['email'] );

		return apply_filters( 'novabankaipg_formatted_email', $formatted, $email );
	}

	/**
	 * Get currency code based on the currency name.
	 *
	 * @param string $currency Currency name (e.g., 'EUR', 'USD').
	 * @return string|null Currency code or null if not found.
	 * @throws NovaBankaIPGException If currency is not supported.
	 */
	public function get_currency_code( string $currency ): ?string {
		$currency = strtoupper( $currency );
		$code     = self::CURRENCY_CODES[ $currency ] ?? null;

		if ( null === $code ) {
			throw new NovaBankaIPGException(
				sprintf(
					/* translators: %s: currency code */
					esc_html__( 'Unsupported currency: %s', 'novabanka-ipg-gateway' ),
					esc_html( $currency )
				)
			);
		}

		return apply_filters( 'novabankaipg_currency_code', $code, $currency );
	}

	/**
	 * Format an item amount by multiplying the amount by quantity.
	 *
	 * @param float $amount   The base amount to format.
	 * @param int   $quantity The quantity to multiply by.
	 * @return string The formatted total amount.
	 */
	public function format_item_amount( float $amount, int $quantity = 1 ): string {
		$total = $amount * $quantity;
		$total = apply_filters( 'novabankaipg_before_format_item_amount', $total, $amount, $quantity );

		return $this->format_amount( $total );
	}

	/**
	 * Validates a language code to ensure it matches IPG specifications.
	 *
	 * @param string $lang_code The language code to validate.
	 * @return string The validated language code or 'EN' if invalid.
	 */
	public function validate_language_code( string $lang_code ): string {
		$lang_code = strtoupper( $lang_code );
		$lang_code = apply_filters( 'novabankaipg_before_validate_language', $lang_code );

		if ( preg_match( '/^[A-Z]{2,3}$/', $lang_code ) ) {
			return $lang_code;
		}

		return apply_filters( 'novabankaipg_default_language', 'EN' );
	}

	/**
	 * Validate and sanitize order data.
	 *
	 * @param array $data Order data to validate.
	 * @return array Sanitized order data.
	 * @throws NovaBankaIPGException If required fields are missing.
	 */
	public function validate_order_data( array $data ): array {
		$data = apply_filters( 'novabankaipg_before_validate_order', $data );

		// Check required fields.
		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( empty( $data[ $field ] ) ) {
				throw new NovaBankaIPGException(
					sprintf(
						/* translators: %s: field name */
						esc_html__( 'Required field missing: %s', 'novabanka-ipg-gateway' ),
						esc_html( $field )
					)
				);
			}
		}

		// Sanitize data.
		$sanitized = array_map(
			function ( $value ) {
				return sanitize_text_field( $value );
			},
			array_filter( $data )
		);

		return apply_filters( 'novabankaipg_validated_order_data', $sanitized, $data );
	}

	/**
	 * Prepare data for API request.
	 *
	 * @param array $data Request data to prepare.
	 * @return array Prepared API request data.
	 */
	public function prepare_api_request( array $data ): array {
		$data = apply_filters( 'novabankaipg_before_prepare_request', $data );

		$prepared = array_merge(
			$this->get_required_fields(),
			$this->validate_order_data( $data )
		);

		return apply_filters( 'novabankaipg_prepared_request', $prepared, $data );
	}

	/**
	 * Get required fields for API requests.
	 *
	 * @return array Required fields.
	 */
	private function get_required_fields(): array {
		$fields = array_fill_keys( self::REQUIRED_FIELDS, '' );
		return apply_filters( 'novabankaipg_required_fields', $fields );
	}
}
