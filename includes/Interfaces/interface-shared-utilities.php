<?php
/**
 * SharedUtilities Interface
 *
 * Defines the contract for shared utility functions used across the plugin.
 *
 * @package NovaBankaIPG\Interfaces
 * @since 1.0.1
 */

namespace NovaBankaIPG\Interfaces;

interface SharedUtilitiesInterface {
	/**
	 * Validate required fields in data array.
	 *
	 * @param array $data Data to validate.
	 * @param array $fields Required field names.
	 * @return void
	 * @throws NovaBankaIPGException If required field is missing.
	 */
	public static function validate_required_fields( array $data, array $fields ): void;

	/**
	 * Generate message verifier for API communication.
	 *
	 * @param mixed ...$fields Fields to include in verification.
	 * @return string Generated message verifier.
	 */
	public static function generate_message_verifier( ...$fields ): string;

	/**
	 * Get API endpoint URL.
	 *
	 * @param string $path Endpoint path.
	 * @return string Complete API endpoint URL.
	 */
	public static function get_api_endpoint( string $path ): string;

	/**
	 * Format amount according to IPG specifications.
	 *
	 * @param float|string $amount Amount to format.
	 * @return string Formatted amount.
	 */
	public static function format_amount( $amount ): string;
}
