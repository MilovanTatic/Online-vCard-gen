<?php
/**
 * Config Class
 *
 * Manages configuration settings for the NovaBanka IPG plugin.
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
 */
class Config {
	/**
	 * Plugin settings
	 *
	 * @var array
	 */
	private array $settings;

	/**
	 * Required gateway settings
	 */
	private const REQUIRED_SETTINGS = array(
		'terminal_id' => array(
			'title'    => 'Terminal ID',
			'type'     => 'text',
			'required' => true,
			'default'  => '89110001',
		),
		'password'    => array(
			'title'    => 'Password',
			'type'     => 'password',
			'required' => true,
			'default'  => 'test1234',
		),
		'secret_key'  => array(
			'title'    => 'Secret Key',
			'type'     => 'password',
			'required' => true,
			'default'  => 'YXKZPOQ9RRLGPDED5D3PC5BJ',
		),
		'ipg_url'     => array(
			'title'    => 'IPG URL',
			'type'     => 'text',
			'required' => true,
			'default'  => 'https://ipgtest.novabanka.com/IPGWeb/servlet/',
		),
	);

	/**
	 * Optional gateway settings
	 */
	private const OPTIONAL_SETTINGS = array(
		'enabled'             => array(
			'title'   => 'Enable/Disable',
			'type'    => 'checkbox',
			'label'   => 'Enable NovaBanka IPG Payment',
			'default' => 'no',
		),
		'title'               => array(
			'title'       => 'Title',
			'type'        => 'text',
			'description' => 'This controls the title which the user sees during checkout.',
			'default'     => 'Credit Card',
		),
		'description'         => array(
			'title'       => 'Description',
			'type'        => 'textarea',
			'description' => 'This controls the description which the user sees during checkout.',
			'default'     => 'Pay securely using your credit card.',
		),
		'message_type'        => array(
			'title'   => 'Message Type',
			'type'    => 'select',
			'options' => array(
				'VISEC' => 'VISEC / VIREC first transaction',
			),
			'default' => 'VISEC',
		),
		'message_version'     => array(
			'title'   => 'Message Version',
			'type'    => 'select',
			'options' => array( '1' => '1' ),
			'default' => '1',
		),
		'action'              => array(
			'title'   => 'Action',
			'type'    => 'select',
			'options' => array(
				'1' => 'PURCHASE',
				'4' => 'AUTHORIZATION',
			),
			'default' => '1',
		),
		'notification_format' => array(
			'title'   => 'Notification Format',
			'type'    => 'select',
			'options' => array( 'json' => 'JSON' ),
			'default' => 'json',
		),
		'payment_page_mode'   => array(
			'title'   => 'Payment Page Mode',
			'type'    => 'select',
			'options' => array( '0' => 'STANDARD' ),
			'default' => '0',
		),
		'language'            => array(
			'title'   => 'Language',
			'type'    => 'text',
			'default' => 'USA',
		),
		'card_sha2'           => array(
			'title'   => 'Card SHA2',
			'type'    => 'select',
			'options' => array(
				'Y' => 'Yes',
				'N' => 'No',
			),
			'default' => 'Y',
		),
		'payment_timeout'     => array(
			'title'   => 'Payment Timeout',
			'type'    => 'text',
			'default' => '30',
		),
	);

	/**
	 * Constructor
	 *
	 * @param array $settings Plugin settings
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get all settings
	 *
	 * @return array
	 */
	public function get_all_settings(): array {
		return $this->settings;
	}

	/**
	 * Get setting value
	 *
	 * @param string $key Setting key
	 * @param mixed  $default Default value
	 * @return mixed
	 */
	public function get_setting( string $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Get API credentials
	 *
	 * @return array
	 */
	public function get_api_credentials(): array {
		return array(
			'terminal_id' => $this->get_setting( 'terminal_id' ),
			'password'    => $this->get_setting( 'password' ),
			'secret_key'  => $this->get_setting( 'secret_key' ),
		);
	}

	/**
	 * Get API endpoint
	 *
	 * @return string
	 */
	public function get_api_endpoint(): string {
		return $this->get_setting( 'ipg_url' );
	}

	/**
	 * Get notification URL
	 *
	 * @return string
	 */
	public function get_notification_url(): string {
		return add_query_arg( 'wc-api', 'novabankaipg', home_url( '/' ) );
	}

	/**
	 * Get error URL
	 *
	 * @return string
	 */
	public function get_error_url(): string {
		return add_query_arg( 'wc-api', 'novabankaipg_error', home_url( '/' ) );
	}

	/**
	 * Validate configuration
	 *
	 * @return bool
	 */
	public function validate_config(): bool {
		foreach ( self::REQUIRED_SETTINGS as $key => $setting ) {
			if ( $setting['required'] && empty( $this->get_setting( $key ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get form fields for WooCommerce settings
	 *
	 * @return array
	 */
	public function get_form_fields(): array {
		return array_merge( self::REQUIRED_SETTINGS, self::OPTIONAL_SETTINGS );
	}

	/**
	 * Get sensitive fields that should be redacted in logs
	 *
	 * @return array
	 */
	public static function get_sensitive_fields(): array {
		return array( 'password', 'secret_key' );
	}
}
