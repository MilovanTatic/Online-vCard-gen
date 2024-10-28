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
 * Version:     1.0.1
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
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\MessageHandler;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Utils\ThreeDSHandler;
use NovaBankaIPG\Services\NotificationService;
use NovaBankaIPG\Services\PaymentService;

/**
 * Main plugin class for NovaBanka IPG33 Payment Gateway.
 *
 * Handles plugin initialization, component loading, and WooCommerce integration.
 *
 * @since 1.0.0
 */
class NovaBankaIPG {
	/**
	 * Singleton instance of the plugin.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Container for services.
	 *
	 * @var array
	 */
	private $container = array();

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	private const VERSION = '1.0.0';

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
	private const PLUGIN_URL = WP_PLUGIN_URL . '/novabanka-ipg';

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
	 * Constructor.
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
		if ( ! defined( 'NOVABANKAIPG_VERSION' ) ) {
			define( 'NOVABANKAIPG_VERSION', self::VERSION );
		}
		if ( ! defined( 'NOVABANKAIPG_PLUGIN_DIR' ) ) {
			define( 'NOVABANKAIPG_PLUGIN_DIR', self::PLUGIN_DIR );
		}
		if ( ! defined( 'NOVABANKAIPG_PLUGIN_URL' ) ) {
			define( 'NOVABANKAIPG_PLUGIN_URL', self::PLUGIN_URL );
		}
	}

	/**
	 * Initialize autoloader.
	 */
	private function init_autoloader(): void {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Initialize service container.
	 */
	private function init_container(): void {
		// Initialize core services.
		$this->container['logger']       = new Logger();
		$this->container['data_handler'] = new DataHandler();

		// Get gateway settings.
		$settings = Config::get_all_settings();

		// Initialize API handler with dependencies.
		$this->container['api_handler'] = new APIHandler(
			$settings['api_endpoint'] ?? '',
			$settings['terminal_id'] ?? '',
			$settings['terminal_password'] ?? '',
			$settings['secret_key'] ?? '',
			$this->container['logger'],
			$this->container['data_handler'],
			$settings['test_mode'] ?? 'yes'
		);

		// Initialize message handler.
		$this->container['message_handler'] = new MessageHandler(
			$settings['terminal_id'] ?? '',
			$settings['terminal_password'] ?? '',
			$settings['secret_key'] ?? '',
			$this->container['data_handler'],
			$this->container['logger']
		);

		// Initialize 3DS handler.
		$this->container['threeds_handler'] = new ThreeDSHandler(
			$this->container['api_handler'],
			$this->container['logger']
		);

		// Initialize payment service.
		$this->container['payment_service'] = new PaymentService(
			$this->container['api_handler'],
			$this->container['logger'],
			$this->container['data_handler']
		);

		// Initialize notification service.
		$this->container['notification_service'] = new NotificationService(
			$this->container['api_handler'],
			$this->container['logger'],
			$this->container['data_handler']
		);
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks(): void {
		// Check WooCommerce dependency.
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );

		// Initialize plugin after WooCommerce loads.
		add_action( 'woocommerce_init', array( $this, 'init_plugin' ) );

		// Register payment gateway.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

		// Register scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ) );

		// Handle API endpoints.
		add_action( 'woocommerce_api_novabankaipg', array( $this, 'handle_api_request' ) );

		// Add settings link to plugins page.
		add_filter(
			'plugin_action_links_' . plugin_basename( __FILE__ ),
			array( $this, 'add_settings_link' )
		);
	}

	/**
	 * Check plugin dependencies.
	 */
	public function check_dependencies(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function init_plugin(): void {
		load_plugin_textdomain(
			'novabanka-ipg-gateway',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages/'
		);
	}

	/**
	 * Add the gateway to WooCommerce.
	 *
	 * @param array $methods Existing payment methods.
	 * @return array Modified payment methods.
	 */
	public function add_gateway( array $methods ): array {
		$methods[] = NovaBankaIPGGateway::class;
		return $methods;
	}

	/**
	 * Register frontend scripts and styles.
	 */
	public function register_scripts(): void {
		if ( ! is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'novabankaipg-styles',
			NOVABANKAIPG_PLUGIN_URL . '/assets/css/ipg-styles.css',
			array(),
			NOVABANKAIPG_VERSION
		);

		wp_enqueue_script(
			'novabankaipg-scripts',
			NOVABANKAIPG_PLUGIN_URL . '/assets/js/ipg-scripts.js',
			array( 'jquery' ),
			NOVABANKAIPG_VERSION,
			true
		);

		// Add nonce for AJAX requests.
		wp_localize_script(
			'novabankaipg-scripts',
			'novabankaipg_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'novabankaipg-nonce' ),
			)
		);
	}

	/**
	 * Register admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
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

		// Add nonce for admin AJAX requests.
		wp_localize_script(
			'novabankaipg-admin',
			'novabankaipg_admin_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'novabankaipg-admin-nonce' ),
			)
		);
	}

	/**
	 * Handle API requests.
	 */
	public function handle_api_request(): void {
		// Verify nonce for API requests.
		if ( ! check_ajax_referer( 'novabankaipg-nonce', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce.' );
			return;
		}

		// Sanitize and validate POST data.
		$post_data = array_map( 'sanitize_text_field', wp_unslash( $_POST ) );

		try {
			$this->container['notification_service']->handle_notification( $post_data );
			wp_send_json_success();
		} catch ( \Exception $e ) {
			$this->container['logger']->error( 'API request failed: ' . esc_html( $e->getMessage() ) );
			wp_send_json_error( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Add settings link to plugin list.
	 *
	 * @param array $links Existing plugin links.
	 * @return array Modified plugin links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_url  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=novabankaipg' );
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'novabanka-ipg-gateway' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Display WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice(): void {
		?>
		<div class="error">
			<p>
				<?php
				esc_html_e(
					'NovaBanka IPG requires WooCommerce to be installed and active.',
					'novabanka-ipg-gateway'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Autoloader for plugin classes.
	 *
	 * @param string $class_name Full class name.
	 */
	private function autoload( string $class_name ): void {
		// Only handle classes in our namespace.
		if ( 0 !== strpos( $class_name, 'NovaBankaIPG\\' ) ) {
			return;
		}

		// Convert class name to file path.
		$file_path = str_replace(
			array( 'NovaBankaIPG\\', '\\' ),
			array( '', DIRECTORY_SEPARATOR ),
			$class_name
		);

		// Convert to lowercase.
		$file_name = 'class-' . strtolower( basename( $file_path ) ) . '.php';
		$file_path = dirname( $file_path ) . DIRECTORY_SEPARATOR . $file_name;

		$file = NOVABANKAIPG_PLUGIN_DIR . '/includes/' . $file_path;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

// Initialize plugin.
add_action(
	'plugins_loaded',
	function () {
		NovaBankaIPG::instance();
	}
);
