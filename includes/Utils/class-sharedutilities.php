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
// ... other use statements ...

/**
 * SharedUtilities Class
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class SharedUtilities implements SharedUtilitiesInterface {
    /**
     * Validate required fields in data array.
     *
     * @param array $data Data to validate.
     * @param array $fields Required field names.
     * @return void
     * @throws NovaBankaIPGException If required field is missing.
     */
    public static function validate_required_fields(array $data, array $fields): void {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                throw new NovaBankaIPGException(
                    sprintf('Missing required field: %s', esc_html($field)),
                    'MISSING_FIELD',
                    ['field' => esc_html($field)]
                );
            }
        }
    }

    /**
     * Generate message verifier for API communication.
     *
     * @param mixed ...$fields Fields to include in verification.
     * @return string Generated message verifier.
     */
    public static function generate_message_verifier(...$fields): string {
        // Direct concatenation without spaces between fields
        $message = implode('', $fields);

        Logger::debug(
            'Message verifier generation:',
            array(
                'initial_string' => $message,
                'initial_hex'    => bin2hex($message),
            )
        );

        // Remove all spaces
        $message = preg_replace('/\s+/', '', $message);

        Logger::debug(
            'After space removal:',
            array(
                'processed_string' => $message,
                'processed_hex'    => bin2hex($message),
            )
        );

        // Get raw hash bytes
        $hash_bytes = hash('sha256', $message, true);

        Logger::debug(
            'Hash bytes:',
            array(
                'hex' => strtoupper(bin2hex($hash_bytes)),
            )
        );

        // Base64 encode
        $verifier = base64_encode($hash_bytes);

        Logger::debug(
            'Final verifier:',
            array(
                'verifier' => $verifier,
            )
        );

        return $verifier;
    }

    /**
     * Get API endpoint URL.
     *
     * @param string $path Endpoint path.
     * @return string Complete API endpoint URL.
     */
    public static function get_api_endpoint(string $path): string {
        $settings = get_option('woocommerce_novabankaipg_settings', array());
        $base_url = $settings['api_endpoint'] ?? '';
        
        // Ensure base URL ends with a slash
        $base_url = rtrim($base_url, '/') . '/';
        
        // Ensure path doesn't start with a slash
        $path = ltrim($path, '/');
        
        return $base_url . $path;
    }

    /**
     * Format amount according to IPG specifications.
     *
     * @param float|string $amount Amount to format.
     * @return string Formatted amount.
     */
    public static function format_amount($amount): string {
        // Convert to float first to handle string inputs
        $amount = (float) $amount;
        
        // Format to 2 decimal places without thousands separator
        return number_format($amount, 2, '.', '');
    }
}
