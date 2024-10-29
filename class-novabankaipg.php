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
 * Text Domain: novabankaipg
 * Domain Path: /languages
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

namespace NovaBankaIPG;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
	 * Singleton instance of the plugin.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Container for dependency injection.
	 *
	 * @var array
	 */
	private array $container = array();

	/**
	 * Plugin version number for tracking releases.
	 *
	 * @var string
	 */
	private const VERSION = '1.0.2';

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private const PLUGIN_DIR = __DIR__;

	/**
	 * Plugin directory URL.
	 *
	 * @var string
	 */
	private const PLUGIN_URL = WP_PLUGIN_URL . '/gateway-33';

	/**
	 * Get the singleton instance of the plugin.
	 *
	 * @return self The singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Plugin constructor.
	 */
	private function __construct() {
		$this->define_constants();
		$this->init_autoloader();
		$this->init_container();
		$this->init_hooks();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants(): void {
		define( 'NOVABANKAIPG_VERSION', self::VERSION );
		define( 'NOVABANKAIPG_PLUGIN_DIR', self::PLUGIN_DIR );
		define( 'NOVABANKAIPG_PLUGIN_URL', self::PLUGIN_URL );
	}

	/**
	 * Initialize the autoloader.
	 */
	private function init_autoloader(): void {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Initialize the dependency container.
	 */
	private function init_container(): void {
		// Initialize Config.
		$this->container['config'] = new Config(
			array(
				'plugin_version' => self::VERSION,
				'plugin_dir'     => self::PLUGIN_DIR,
				'plugin_url'     => self::PLUGIN_URL,
				'terminal_id'    => get_option( 'woocommerce_novabankaipg_terminal_id', '' ),
				'password'       => get_option( 'woocommerce_novabankaipg_password', '' ),
				'secret_key'     => get_option( 'woocommerce_novabankaipg_secret_key', '' ),
				'ipg_url'        => get_option( 'woocommerce_novabankaipg_ipg_url', '' ),
				'test_mode'      => get_option( 'woocommerce_novabankaipg_test_mode', 'yes' ),
			)
		);

		// Initialize Logger.
		$this->container['logger'] = new Logger( $this->container['config'] );

		// Initialize API Handler.
		$this->container['api_handler'] = new APIHandler(
			$this->container['config']->get( 'ipg_url' ),
			$this->container['logger']
		);

		// Initialize Message Handler.
		$this->container['message_handler'] = new MessageHandler(
			$this->container['config']->get( 'terminal_id' ),
			$this->container['config']->get( 'password' ),
			$this->container['config']->get( 'secret_key' ),
			$this->container['logger']
		);

		// Initialize Services.
		$this->init_services();

		// Initialize Gateway.
		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = new NovaBankaIPGGateway(
					$this->container['payment_service'],
					$this->container['notification_service']
				);
				return $gateways;
			}
		);
	}

	/**
	 * Initialize plugin hooks.
	 */
	private function init_hooks(): void {
		add_action( 'admin_notices', array( $this, 'check_dependencies' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );

		// Register admin assets.
		if ( is_admin() ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );
		}

		// Register API endpoints.
		add_action( 'wp_ajax_novabankaipg_handle_notification', array( $this, 'handle_api_request' ) );
		add_action( 'wp_ajax_noexec_novabankaipg_handle_notification', array( $this, 'handle_api_request' ) );
	}

	/**
	 * Check WooCommerce dependency.
	 */
	public function check_dependencies(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Register admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function register_admin_scripts( string $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'novabankaipg-admin',
			NOVABANKAIPG_PLUGIN_URL . '/assets/css/ipg-admin.css',
			array(),
			NOVABANKAIPG_VERSION
		);

		wp_enqueue_script(
			'novabankaipg-admin',
			NOVABANKAIPG_PLUGIN_URL . '/assets/js/ipg-admin.js',
			array( 'jquery' ),
			NOVABANKAIPG_VERSION,
			true
		);

		wp_localize_script(
			'novabankaipg-admin',
			'novaBankaIPG',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'novabankaipg-admin-nonce' ),
			)
		);
	}

	/**
	 * Handle API request.
	 */
	public function handle_api_request(): void {
		check_ajax_referer( 'novabankaipg-nonce', 'nonce' );

		try {
			$post_data = $this->validate_request( $_POST );
			$this->container['notification_service']->handle_notification( $post_data );
			wp_send_json_success();
		} catch ( \Exception $e ) {
			$this->container['logger']->error(
				'API request failed: ' . $e->getMessage(),
				array( 'exception' => $e )
			);
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * Validate the request data.
	 *
	 * @param array $data The request data.
	 * @return array The sanitized request data.
	 */
	private function validate_request( array $data ): array {
		return array_map( 'sanitize_text_field', wp_unslash( $data ) );
	}

	/**
	 * Add a settings link to the plugin in the WordPress admin.
	 *
	 * @param array $links Array of existing links.
	 * @return array Modified array of links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=novabankaipg' );
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
	 * Display a notice if WooCommerce is missing.
	 */
	public function woocommerce_missing_notice(): void {
		printf(
			'<div class="error"><p>%s</p></div>',
			esc_html__( 'NovaBanka IPG requires WooCommerce to be installed and active.', 'novabanka-ipg-gateway' )
		);
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class_name The class name to autoload.
	 */
	private function autoload( string $class_name ): void {
		if ( 0 !== strpos( $class_name, 'NovaBankaIPG\\' ) ) {
			return;
		}

		$file_path = str_replace(
			array( 'NovaBankaIPG\\', '\\' ),
			array( '', DIRECTORY_SEPARATOR ),
			$class_name
		);

		$file = NOVABANKAIPG_PLUGIN_DIR . '/includes/' . dirname( $file_path )
			. DIRECTORY_SEPARATOR . 'class-' . strtolower( basename( $file_path ) ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Initialize plugin services.
	 */
	private function init_services(): void {
		// Initialize Payment Service.
		$this->container['payment_service'] = new PaymentService(
			$this->container['api_handler'],
			$this->container['message_handler'],
			$this->container['logger']
		);

		// Initialize Notification Service.
		$this->container['notification_service'] = new NotificationService(
			$this->container['logger'],
			$this->container['message_handler']
		);

		// Initialize 3DS Handler.
		$this->container['threeds_handler'] = new ThreeDSHandler(
			$this->container['api_handler'],
			$this->container['logger']
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
