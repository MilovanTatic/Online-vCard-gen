<?php
/**
 * NovaBanka IPG33 Payment Gateway Integration
 *
 * @package    NovaBankaIPG
 * @author     Milovan Tatić
 * @copyright  Milovan Tatić
 * @license    Free for private use. Commercial use is not allowed without permission.
 *
 * @wordpress-plugin
 * Plugin Name: NovaBanka IPG33 Payment Gateway
 * Description: 3D Secure payment gateway integration for WooCommerce
 * Version:     1.0.2
 * Author:      Milovan Tatić
 * Text Domain: novabanka-ipg-gateway
 * Domain Path: /languages
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

namespace NovaBankaIPG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants before namespace usage.
if ( ! defined( 'NOVABANKAIPG_VERSION' ) ) {
	define( 'NOVABANKAIPG_VERSION', '1.0.2' );
	define( 'NOVABANKAIPG_PLUGIN_FILE', __FILE__ );
	define( 'NOVABANKAIPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'NOVABANKAIPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

use NovaBankaIPG\Core\NovaBankaIPGGateway;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\MessageHandler;
use NovaBankaIPG\Utils\ThreeDSHandler;
use NovaBankaIPG\Services\NotificationService;
use NovaBankaIPG\Services\PaymentService;

/**
 * Main plugin class for NovaBanka IPG33 Payment Gateway.
 */
class NovaBankaIPG {
	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Service container.
	 *
	 * @var array
	 */
	private array $container = array();

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->init_autoloader();
		$this->init_container();
		$this->init_hooks();
	}

	/**
	 * Initialize plugin hooks.
	 */
	private function init_hooks(): void {
		// Check WooCommerce dependency.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		// Add payment gateway.
		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = new NovaBankaIPGGateway(
					$this->container['payment_service'],
					$this->container['notification_service'],
					$this->container['config']
				);
				return $gateways;
			}
		);

		// Add settings link.
		add_filter(
			'plugin_action_links_' . plugin_basename( NOVABANKAIPG_PLUGIN_FILE ),
			array( $this, 'add_settings_link' )
		);
	}

	/**
	 * Initialize dependency container.
	 */
	private function init_container(): void {
		// Initialize Config with settings from database.
		$this->container['config'] = new Config(
			array_map(
				function ( $key ) {
					return get_option( "woocommerce_novabankaipg_{$key}" );
				},
				array_keys( Config::REQUIRED_SETTINGS )
			)
		);

		// Initialize Logger.
		$this->container['logger'] = new Logger( $this->container['config'] );

		// Initialize API Handler.
		$this->container['api_handler'] = new APIHandler(
			$this->container['config']->get_api_endpoint(),
			$this->container['logger']
		);

		// Initialize Message Handler.
		$credentials                        = $this->container['config']->get_api_credentials();
		$this->container['message_handler'] = new MessageHandler(
			$credentials['terminal_id'],
			$credentials['password'],
			$credentials['secret_key'],
			$this->container['logger']
		);

		$this->init_services();
	}

	/**
	 * Initialize services.
	 */
	private function init_services(): void {
		$this->container['payment_service'] = new PaymentService(
			$this->container['api_handler'],
			$this->container['message_handler'],
			$this->container['logger']
		);

		$this->container['notification_service'] = new NotificationService(
			$this->container['logger'],
			$this->container['message_handler']
		);

		$this->container['threeds_handler'] = new ThreeDSHandler(
			$this->container['api_handler'],
			$this->container['logger']
		);
	}

	/**
	 * Initialize autoloader
	 */
	private function init_autoloader(): void {
		spl_autoload_register(
			function ( $class ) {
				// Convert namespace to path.
				$path = str_replace( 'NovaBankaIPG\\', '', $class );
				$path = str_replace( '\\', '/', $path );

				// Build file path with class- prefix.
				$file = NOVABANKAIPG_PLUGIN_DIR . 'includes/' . dirname( $path ) . '/class-' .
						strtolower( basename( $path ) ) . '.php';

				// Load file if it exists.
				if ( file_exists( $file ) ) {
					require_once $file;
				}
			}
		);
	}

	/**
	 * Get singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add settings link to plugin page.
	 *
	 * @param array $links Array of plugin action links.
	 * @return array Modified array of plugin action links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=novabanka-ipg-gateway' );
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'novabanka-ipg-gateway' )
			)
		);
		return $links;
	}

	/**
	 * Display WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice(): void {
		printf(
			'<div class="error"><p>%s</p></div>',
			esc_html__( 'NovaBanka IPG requires WooCommerce to be installed and active.', 'novabanka-ipg-gateway' )
		);
	}
}

// Initialize plugin.
add_action(
	'plugins_loaded',
	function () {
		NovaBankaIPG::instance();
	}
);
