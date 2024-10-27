<?php
/**
 * Config Utility Class
 *
 * This class is responsible for handling the plugin configuration settings.
 * It provides a convenient way to retrieve and manage configuration options for the NovaBanka IPG plugin.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

/**
 * Class Config
 *
 * Handles plugin configuration settings and provides methods to manage them.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class Config {
	/**
	 * Retrieve a setting value by key.
	 *
	 * @param string $key The setting key to retrieve.
	 * @return mixed The setting value or null if not found.
	 */
	public static function get_setting( $key ) {
		$settings = get_option( 'woocommerce_novabankaipg_settings', array() );
		return $settings[ $key ] ?? null;
	}

	/**
	 * Retrieve all plugin settings.
	 *
	 * @return array All settings as an associative array.
	 */
	public static function get_all_settings() {
		return get_option( 'woocommerce_novabankaipg_settings', array() );
	}

	/**
	 * Update a specific plugin setting.
	 *
	 * @param string $key The setting key to update.
	 * @param mixed  $value The new value for the setting.
	 * @return bool True on success, false on failure.
	 */
	public static function update_setting( $key, $value ) {
		$settings         = get_option( 'woocommerce_novabankaipg_settings', array() );
		$settings[ $key ] = $value;
		return update_option( 'woocommerce_novabankaipg_settings', $settings );
	}
}
