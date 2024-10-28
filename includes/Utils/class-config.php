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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Config
 *
 * Handles configuration management for the NovaBanka IPG plugin.
 */
class Config {
	/**
	 * Option prefix for all plugin settings.
	 *
	 * @var string
	 */
	private const OPTION_PREFIX = 'wc_novabankaipg_';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private const DEFAULT_SETTINGS = array(
		'enabled'                => 'no',
		'test_mode'              => 'yes',
		'debug'                  => 'no',
		'title'                  => 'NovaBanka IPG',
		'description'            => 'Pay securely using NovaBanka IPG.',

		// Test mode settings.
		'test_api_endpoint'      => 'https://ipgtest.novabanka.com/IPGWeb/servlet/PaymentInitRequest',
		'test_terminal_id'       => '',
		'test_terminal_password' => '',
		'test_secret_key'        => '',

		// Production mode settings.
		'live_api_endpoint'      => 'https://ipg.novabanka.com/IPGWeb/servlet/PaymentInitRequest',
		'live_terminal_id'       => '',
		'live_terminal_password' => '',
		'live_secret_key'        => '',

		// Action settings.
		'action'                 => '1', // 1 = PURCHASE, 4 = AUTHORIZATION

		// Response URLs.
		'response_url'           => '',  // Will be dynamically set.
		'error_url'              => '',   // Will be dynamically set.

		// Language settings.
		'langid'                 => 'EN',

		// 3DS settings.
		'threeds_enabled'        => 'yes',
		'threeds_auth_method'    => '02',
		'threeds_prior_auth'     => '01',
	);

	/**
	 * Retrieve a setting value by key.
	 *
	 * @param string $key     The setting key to retrieve.
	 * @param mixed  $default Default value if setting not found.
	 * @return mixed The setting value or default if not found.
	 */
	public static function get_setting( string $key, $default = null ) {
		$key   = sanitize_key( $key );
		$value = get_option( self::OPTION_PREFIX . $key, $default );

		/**
		 * Filter the retrieved setting value.
		 *
		 * @since 1.0.1
		 * @param mixed  $value   The setting value.
		 * @param string $key     The setting key.
		 * @param mixed  $default The default value.
		 */
		return apply_filters( 'novabankaipg_get_setting', $value, $key, $default );
	}

	/**
	 * Retrieve all plugin settings.
	 *
	 * @return array All settings as an associative array.
	 */
	public static function get_all_settings(): array {
		$settings = get_option( 'woocommerce_novabankaipg_settings', self::DEFAULT_SETTINGS );

		/**
		 * Filter all plugin settings.
		 *
		 * @since 1.0.1
		 * @param array $settings The settings array.
		 */
		return apply_filters( 'novabankaipg_all_settings', $settings );
	}

	/**
	 * Update a specific plugin setting.
	 *
	 * @param string $key   The setting key to update.
	 * @param mixed  $value The new value for the setting.
	 * @return bool True on success, false on failure.
	 */
	public static function update_setting( string $key, $value ): bool {
		$key      = sanitize_key( $key );
		$settings = self::get_all_settings();

		/**
		 * Filter the value before saving.
		 *
		 * @since 1.0.1
		 * @param mixed  $value    The setting value to save.
		 * @param string $key      The setting key.
		 * @param array  $settings Current settings.
		 */
		$value = apply_filters( 'novabankaipg_pre_update_setting', $value, $key, $settings );

		$settings[ $key ] = $value;
		return update_option( 'woocommerce_novabankaipg_settings', $settings );
	}

	/**
	 * Determine if the plugin is in test mode.
	 *
	 * @return bool True if test mode is enabled, false otherwise.
	 */
	public static function is_test_mode(): bool {
		$test_mode = self::get_setting( 'test_mode', 'yes' ) === 'yes';

		/**
		 * Filter test mode status.
		 *
		 * @since 1.0.1
		 * @param bool $test_mode Whether test mode is enabled.
		 */
		return apply_filters( 'novabankaipg_is_test_mode', $test_mode );
	}

	/**
	 * Determine if debug logging is enabled.
	 *
	 * @return bool True if debug logging is enabled, false otherwise.
	 */
	public static function is_debug_mode(): bool {
		$debug_mode = self::get_setting( 'debug', 'no' ) === 'yes';

		/**
		 * Filter debug mode status.
		 *
		 * @since 1.0.1
		 * @param bool $debug_mode Whether debug mode is enabled.
		 */
		return apply_filters( 'novabankaipg_is_debug_mode', $debug_mode );
	}

	/**
	 * Get API endpoint URL based on mode.
	 *
	 * @return string API endpoint URL.
	 */
	public static function get_api_endpoint(): string {
		$is_test  = self::is_test_mode();
		$endpoint = $is_test ?
			self::get_setting( 'test_api_endpoint' ) :
			self::get_setting( 'live_api_endpoint' ); // Changed from 'api_endpoint'.

		/**
		 * Filter API endpoint URL.
		 *
		 * @since 1.0.1
		 * @param string $endpoint The API endpoint URL.
		 * @param bool   $is_test  Whether test mode is enabled.
		 */
		return apply_filters( 'novabankaipg_api_endpoint', $endpoint, $is_test );
	}

	/**
	 * Get 3DS configuration.
	 *
	 * @return array 3DS configuration settings.
	 */
	public static function get_threeds_config(): array {
		$config = array(
			'enabled'     => self::get_setting( 'threeds_enabled', 'yes' ) === 'yes',
			'auth_method' => self::get_setting( 'threeds_auth_method', '02' ),
			'prior_auth'  => self::get_setting( 'threeds_prior_auth', '01' ),
		);

		/**
		 * Filter 3DS configuration.
		 *
		 * @since 1.0.1
		 * @param array $config The 3DS configuration array.
		 */
		return apply_filters( 'novabankaipg_threeds_config', $config );
	}

	/**
	 * Delete all plugin settings.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete_all_settings(): bool {
		/**
		 * Action before deleting all settings.
		 *
		 * @since 1.0.1
		 */
		do_action( 'novabankaipg_before_delete_settings' );

		$result = delete_option( 'woocommerce_novabankaipg_settings' );

		/**
		 * Action after deleting all settings.
		 *
		 * @since 1.0.1
		 * @param bool $result Whether the deletion was successful.
		 */
		do_action( 'novabankaipg_after_delete_settings', $result );

		return $result;
	}

	/**
	 * Validate required settings.
	 *
	 * @return bool True if all required settings are valid.
	 */
	public static function validate_settings(): bool {
		$required = array(
			'terminal_id',
			'terminal_password',
			'secret_key',
			'api_endpoint',
		);

		foreach ( $required as $key ) {
			if ( empty( self::get_setting( $key ) ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get form fields for the payment gateway settings.
	 *
	 * @since 1.0.0
	 * @return array Array of form field settings.
	 */
	public static function get_form_fields(): array {
		return array(
			'enabled'                => array(
				'title'   => __( 'Enable/Disable', 'novabanka-ipg-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable NovaBanka IPG Gateway', 'novabanka-ipg-gateway' ),
				'default' => self::DEFAULT_SETTINGS['enabled'],
			),
			// Test Mode.
			'test_mode'              => array(
				'title'       => __( 'Test Mode', 'novabanka-ipg-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Test Mode', 'novabanka-ipg-gateway' ),
				'default'     => self::DEFAULT_SETTINGS['test_mode'],
				'description' => __( 'Place the payment gateway in test mode.', 'novabanka-ipg-gateway' ),
			),
			// Test Credentials.
			'test_terminal_id'       => array(
				'title'       => __( 'Test Terminal ID', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your test terminal ID from NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => self::DEFAULT_SETTINGS['test_terminal_id'],
				'desc_tip'    => true,
			),
			'test_terminal_password' => array(
				'title'       => __( 'Test Terminal Password', 'novabanka-ipg-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your test terminal password from NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => self::DEFAULT_SETTINGS['test_terminal_password'],
				'desc_tip'    => true,
			),
			'test_secret_key'        => array(
				'title'       => __( 'Test Secret Key', 'novabanka-ipg-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your test secret key from NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => self::DEFAULT_SETTINGS['test_secret_key'],
				'desc_tip'    => true,
			),
			// Live Credentials.
			'live_terminal_id'       => array(
				'title'       => __( 'Live Terminal ID', 'novabanka-ipg-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your live terminal ID from NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => self::DEFAULT_SETTINGS['live_terminal_id'],
				'desc_tip'    => true,
			),
			'live_terminal_password' => array(
				'title'       => __( 'Live Terminal Password', 'novabanka-ipg-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your live terminal password from NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => self::DEFAULT_SETTINGS['live_terminal_password'],
				'desc_tip'    => true,
			),
			'live_secret_key'        => array(
				'title'       => __( 'Live Secret Key', 'novabanka-ipg-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your live secret key from NovaBanka.', 'novabanka-ipg-gateway' ),
				'default'     => self::DEFAULT_SETTINGS['live_secret_key'],
				'desc_tip'    => true,
			),
		);
	}
}
