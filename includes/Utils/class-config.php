<?php
/**
 * Config Class
 *
 * Manages configuration settings for the NovaBanka IPG plugin.
 * Provides methods for retrieving and updating plugin settings.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Interfaces\ConfigInterface;

/**
 * Class Config
 *
 * Handles configuration management for the NovaBanka IPG plugin.
 * Provides methods for retrieving and managing plugin settings.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class Config implements ConfigInterface {
	/**
	 * Retrieve a setting value by key.
	 *
	 * @param string $key The setting key to retrieve.
	 * @return mixed The setting value or null if not found.
	 */
	public static function get_setting( string $key ) {
		// Missing string type hint.
		$settings = get_option( 'woocommerce_novabankaipg_settings', array() );
		return $settings[ $key ] ?? null;
	}

	/**
	 * Retrieve all plugin settings.
	 *
	 * @return array All settings as an associative array.
	 */
	public static function get_all_settings(): array {
		return get_option( 'woocommerce_novabankaipg_settings', array() );
	}

	/**
	 * Update a specific plugin setting.
	 *
	 * @param string $key The setting key to update.
	 * @param mixed  $value The new value for the setting.
	 * @return bool True on success, false on failure.
	 */
	public static function update_setting( string $key, $value ): bool {
		$settings         = get_option( 'woocommerce_novabankaipg_settings', array() );
		$settings[ $key ] = $value;
		return update_option( 'woocommerce_novabankaipg_settings', $settings );
	}

	/**
	 * Determine if the plugin is in test mode.
	 *
	 * @return bool True if test mode is enabled, false otherwise.
	 */
	public static function is_test_mode(): bool {
		return self::get_setting( 'test_mode' ) === 'yes';
	}

	/**
	 * Determine if debug logging is enabled.
	 *
	 * @return bool True if debug logging is enabled, false otherwise.
	 */
	public static function is_debug_mode(): bool {
		return self::get_setting( 'debug' ) === 'yes';
	}
}
