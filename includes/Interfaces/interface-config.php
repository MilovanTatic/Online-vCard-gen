<?php
/**
 * Config Interface
 *
 * Defines the contract for configuration management.
 *
 * @package NovaBankaIPG\Interfaces
 * @since 1.0.1
 */

namespace NovaBankaIPG\Interfaces;

interface ConfigInterface {
    /**
     * Get a setting value by key.
     *
     * @param string $key The setting key.
     * @return mixed The setting value or null if not found.
     */
    public static function get_setting(string $key);

    /**
     * Get all plugin settings.
     *
     * @return array All settings.
     */
    public static function get_all_settings(): array;

    /**
     * Update a setting value.
     *
     * @param string $key The setting key.
     * @param mixed  $value The new value.
     * @return bool Success status.
     */
    public static function update_setting(string $key, $value): bool;

    /**
     * Check if test mode is enabled.
     *
     * @return bool Test mode status.
     */
    public static function is_test_mode(): bool;

    /**
     * Check if debug mode is enabled.
     *
     * @return bool Debug mode status.
     */
    public static function is_debug_mode(): bool;
}
