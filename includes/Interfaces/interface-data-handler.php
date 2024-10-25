<?php
/**
 * Defines the DataHandler interface for managing data operations
 *
 * @package NovaBankaIPG\Interfaces
 */

namespace NovaBankaIPG\Interfaces;

interface DataHandler {
	/**
	 * Format amount for IPG.
	 *
	 * @param float $amount Amount to format.
	 * @return string Formatted amount.
	 */
	public function format_amount( float $amount ): string;

	/**
	 * Get currency code for IPG
	 *
	 * @param string $currency Currency code.
	 * @return string Currency code.
	 */
	public function get_currency_code( string $currency ): string;

	/**
	 * Validate required fields.
	 *
	 * @param array $data Data to validate.
	 * @param array $required Required fields.
	 * @return bool True if validation is successful.
	 */
	public function validate_required_fields( array $data, array $required ): bool;
}
