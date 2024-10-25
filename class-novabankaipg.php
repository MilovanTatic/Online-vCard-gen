<?php
/**
 * Novabanka IPG33 Payment Gateway Integration
 *
 * @package    NovaBankaIPG
 * @author     Milovan Tatić
 * @copyright  Milovan Tatić
 * @license    Free for private use. Commercial use is not allowed without permission.
 *
 * @wordpress-plugin
 * Plugin Name: Novabanka IPG33 Payment Gateway
 * Description: 3D Secure payment gateway integration for WooCommerce
 * Version:     1.0.0
 * Author:      Milovan Tatić
 * Text Domain: novabankaipg
 * Domain Path: /languages
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

namespace NovaBankaIPG;

use NovaBankaIPG\Core\NovaBankaIPGGateway;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\ThreeDSHandler;

defined( 'ABSPATH' ) || exit;

// Define plugin directory using WordPress constants.
define( 'NOVABANKAIPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOVABANKAIPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NOVABANKAIPG_VERSION', '1.0.0' );

// Include necessary interfaces and classes.
require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Interfaces/interface-logger.php';
require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Interfaces/interface-api-handler.php';
require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Interfaces/interface-data-handler.php';
require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Utils/class-logger.php';
require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Utils/class-datahandler.php';
require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Utils/class-apihandler.php';
require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Utils/class-threedshandler.php';

/**
 * Main plugin class for NovaBanka IPG33 Payment Gateway
 *
 * Handles plugin initialization, component loading, and WooCommerce integration.
 *
 * @package NovaBankaIPG
 * @since 1.0.0
 * @var self|null $instance Singleton instance of the plugin
 * @var Core\NovaBankaIPGGateway $gateway Payment gateway instance
 * @var Utils\APIHandler $api_handler API handler instance
 * @var Utils\Logger $logger Logger instance
 * @var Utils\DataHandler $data_handler Data handler instance
 * @var Utils\ThreeDSHandler $threeds_handler ThreeDS handler instance
 */
class NovaBankaIPG {
	/**
	 * Singleton instance of the plugin
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Payment gateway instance
	 *
	 * @var Core\NovaBankaIPGGateway
	 */
	private $gateway;

	/**
	 * API handler instance
	 *
	 * @var Utils\APIHandler
	 */
	private $api_handler;

	/**
	 * Logger instance
	 *
	 * @var Utils\Logger
	 */
	private $logger;

	/**
	 * Data handler instance
	 *
	 * @var Utils\DataHandler
	 */
	private $data_handler;

	/**
	 * ThreeDS handler instance
	 *
	 * @var Utils\ThreeDSHandler
	 */
	private $threeds_handler;

	/**
	 * Get the singleton instance of the plugin.
	 *
	 * @return self The singleton instance.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor for the NovaBankaIPG class.
	 *
	 * Initializes hooks for the plugin.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks for the plugin.
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'check_woocommerce_active' ) );
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Check if WooCommerce is active.
	 */
	public function check_woocommerce_active() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function init_plugin() {
		// Load text domain.
		load_plugin_textdomain( 'novabankaipg', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Register the gateway class.
		require_once NOVABANKAIPG_PLUGIN_DIR . 'includes/Core/class-novabankaipggateway.php';

			// Initialize components.
			$this->init_components();
	}

	/**
	 * Initialize components.
	 */
	private function init_components() {
		// Initialize Logger first.
		$this->logger = new Logger( 'novabankaipg' );

		// Initialize Data Handler.
		$this->data_handler = new DataHandler();

		// Get gateway settings.
		$settings = $this->get_gateway_settings();

		// Initialize API Handler with settings.
		$this->api_handler = new APIHandler(
			$settings['api_endpoint'],
			$settings['terminal_id'],
			$settings['terminal_password'],
			$settings['secret_key'],
			$this->logger,
			$this->data_handler
		);

		// Initialize 3DS Handler.
		$this->threeds_handler = new ThreeDSHandler(
			$this->api_handler,
			$this->logger,
			$this->data_handler
		);

		// Initialize Gateway last as it depends on other components.
		$this->gateway = new NovaBankaIPGGateway(
			$this->api_handler,
			$this->threeds_handler,
			$this->data_handler,
			$this->logger
		);
	}

	/**
	 * Add the gateway to the WooCommerce payment gateways.
	 *
	 * @param array $gateways Existing gateways.
	 * @return array Modified gateways.
	 */
	public function add_gateway( array $gateways ): array {
		$gateways[] = Core\NovaBankaIPGGateway::class;
		return $gateways;
	}

	/**
	 * Enqueue scripts for the frontend.
	 */
	public function enqueue_scripts() {
		if ( is_checkout() ) {
			wp_enqueue_style(
				'novabankaipg-styles',
				NOVABANKAIPG_PLUGIN_URL . 'assets/css/ipg-styles.css',
				array(),
				NOVABANKAIPG_VERSION
			);

			wp_enqueue_script(
				'novabankaipg-scripts',
				NOVABANKAIPG_PLUGIN_URL . 'assets/js/ipg-scripts.js',
				array( 'jquery' ),
				NOVABANKAIPG_VERSION,
				true
			);
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'novabankaipg-admin',
			NOVABANKAIPG_PLUGIN_URL . 'assets/css/ipg-admin.css',
			array(),
			NOVABANKAIPG_VERSION
		);

		wp_enqueue_script(
			'novabankaipg-admin',
			NOVABANKAIPG_PLUGIN_URL . 'assets/js/ipg-admin.js',
			array( 'jquery' ),
			NOVABANKAIPG_VERSION,
			true
		);
	}

	/**
	 * Display a notice if WooCommerce is missing.
	 */
	public function woocommerce_missing_notice() {
		echo '<div class="error"><p>' .
			esc_html__( 'NovaBanka IPG requires WooCommerce to be installed and active.', 'novabanka-ipg-gateway' ) .
			'</p></div>';
	}

	/**
	 * Initialize plugin.
	 *
	 * @return self The singleton instance.
	 */
	public static function init() {
		return self::instance();
	}

	/**
	 * Get gateway settings.
	 *
	 * @return array
	 */
	private function get_gateway_settings(): array {
		$settings = get_option( 'woocommerce_novabankaipg_settings', array() );

		return array(
			'api_endpoint'      => $settings['api_endpoint'] ?? '',
			'terminal_id'       => $settings['terminal_id'] ?? '',
			'terminal_password' => $settings['terminal_password'] ?? '',
			'secret_key'        => $settings['secret_key'] ?? '',
		);
	}
}

// Initialize plugin.
NovaBankaIPG::init();
