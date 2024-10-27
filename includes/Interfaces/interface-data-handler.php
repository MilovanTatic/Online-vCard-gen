<?php
/**
 * Defines the DataHandler interface for managing data operations
 *
 * @package NovaBankaIPG\Interfaces
 */

namespace NovaBankaIPG\Interfaces;

/**
 * DataHandlerInterface
 *
 * @package NovaBankaIPG\Interfaces
 */
interface DataHandlerInterface {
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
	 * @param float $amount   The base amount to format.
	 * @param int   $quantity The quantity to multiply by.
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

	/**
	 * Get currency code based on the currency name.
	 *
	 * @param string $currency Currency name (e.g., 'EUR', 'USD').
	 * @return string|null Currency code or null if not found.
	 */
	public function get_currency_code( string $currency ): ?string;
}
