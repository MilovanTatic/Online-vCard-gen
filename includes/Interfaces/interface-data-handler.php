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
	 * Format phone number.
	 *
	 * @param string $phone Phone number to format.
	 * @return string|null Formatted phone number or null if not formatted.
	 */
	public function format_phone( string $phone ): ?string;

	/**
	 * Format item amount.
	 *
	 * @param float $amount Amount to format.
	 * @param int   $quantity Quantity to format.
	 * @return string Formatted amount.
	 */
	public function format_item_amount( float $amount, int $quantity = 1 ): string;

	/**
	 * Validate language code.
	 *
	 * @param string $lang_code Language code to validate.
	 * @return string Validated language code.
	 */
	public function validate_language_code( string $lang_code ): string;
}
