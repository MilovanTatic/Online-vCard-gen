<?php
/**
 * Logger Utility Class
 *
 * Handles logging operations for the NovaBanka IPG plugin using WooCommerce's logging system.
 * Provides methods for different log levels and handles sensitive data redaction.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use WC_Logger;

/**
 * Logger Class
 *
 * Handles logging operations for the NovaBanka IPG plugin.
 */
class Logger {
	/**
	 * WooCommerce Logger instance.
	 *
	 * @var WC_Logger
	 */
	private $wc_logger;

	/**
	 * Source identifier for log entries.
	 *
	 * @var string
	 */
	private const LOG_SOURCE = 'novabanka-ipg';

	/**
	 * Keys that should be redacted in logs for security.
	 *
	 * @var array
	 */
	private const SENSITIVE_KEYS = array(
		'password',
		'terminal_password',
		'secret_key',
		'card_number',
		'cvv',
		'pan',
		'token',
		'auth_code',
		'msgVerifier',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->wc_logger = wc_get_logger();
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! Config::is_debug_mode() ) {
			return;
		}

		/**
		 * Filter debug message before logging.
		 *
		 * @since 1.0.1
		 * @param string $message The message to log.
		 * @param array  $context The context data.
		 */
		$message = apply_filters( 'novabankaipg_debug_message', $message, $context );

		$this->log( 'debug', $message, $context );
	}

	/**
	 * Log info message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 */
	public function info( string $message, array $context = array() ): void {
		/**
		 * Filter info message before logging.
		 *
		 * @since 1.0.1
		 * @param string $message The message to log.
		 * @param array  $context The context data.
		 */
		$message = apply_filters( 'novabankaipg_info_message', $message, $context );

		$this->log( 'info', $message, $context );
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 */
	public function error( string $message, array $context = array() ): void {
		/**
		 * Filter error message before logging.
		 *
		 * @since 1.0.1
		 * @param string $message The message to log.
		 * @param array  $context The context data.
		 */
		$message = apply_filters( 'novabankaipg_error_message', $message, $context );

		$this->log( 'error', $message, $context );

		/**
		 * Action after logging an error.
		 *
		 * @since 1.0.1
		 * @param string $message The logged message.
		 * @param array  $context The context data.
		 */
		do_action( 'novabankaipg_after_error_log', $message, $context );
	}

	/**
	 * Log warning message.
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 */
	public function warning( string $message, array $context = array() ): void {
		/**
		 * Filter warning message before logging.
		 *
		 * @since 1.0.1
		 * @param string $message The message to log.
		 * @param array  $context The context data.
		 */
		$message = apply_filters( 'novabankaipg_warning_message', $message, $context );

		$this->log( 'warning', $message, $context );
	}

	/**
	 * Internal logging method.
	 *
	 * @param string $level   Log level.
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->wc_logger ) {
			return;
		}

		// Sanitize and validate data.
		$message = sanitize_text_field( $message );
		$context = $this->sanitize_context( $context );

		// Format the log entry.
		$log_entry = $this->format_message( $message, $context );

		/**
		 * Filter log entry before writing.
		 *
		 * @since 1.0.1
		 * @param string $log_entry The formatted log entry.
		 * @param string $level     The log level.
		 * @param string $message   The original message.
		 * @param array  $context   The context data.
		 */
		$log_entry = apply_filters( 'novabankaipg_log_entry', $log_entry, $level, $message, $context );

		// Write to log.
		$this->wc_logger->{$level}(
			$log_entry,
			array(
				'source' => self::LOG_SOURCE,
			)
		);

		/**
		 * Action after writing to log.
		 *
		 * @since 1.0.1
		 * @param string $level     The log level.
		 * @param string $message   The original message.
		 * @param array  $context   The context data.
		 * @param string $log_entry The formatted log entry.
		 */
		do_action( 'novabankaipg_after_log', $level, $message, $context, $log_entry );
	}

	/**
	 * Format log message with context.
	 *
	 * @param string $message Message to format.
	 * @param array  $context Context data.
	 * @return string Formatted message.
	 */
	private function format_message( string $message, array $context ): string {
		$context_string = empty( $context ) ? '' : ' | Context: ' . wp_json_encode( $context );

		return sprintf(
			'[%s] %s%s',
			wp_date( 'Y-m-d H:i:s' ),
			$message,
			$context_string
		);
	}

	/**
	 * Sanitize context data by redacting sensitive information.
	 *
	 * @param array $context Context data to sanitize.
	 * @return array Sanitized context data.
	 */
	private function sanitize_context( array $context ): array {
		array_walk_recursive(
			$context,
			function ( &$value, $key ) {
				if ( $this->should_redact_key( $key ) ) {
					$value = str_repeat( '*', 8 );
				} elseif ( is_string( $value ) ) {
					$value = sanitize_text_field( $value );
				}
			}
		);

		return $context;
	}

	/**
	 * Check if a key should be redacted.
	 *
	 * @param string $key Key to check.
	 * @return bool True if key should be redacted.
	 */
	private function should_redact_key( string $key ): bool {
		$key = strtolower( $key );
		foreach ( self::SENSITIVE_KEYS as $sensitive_key ) {
			if ( false !== strpos( $key, $sensitive_key ) ) {
				return true;
			}
		}
		return false;
	}
}
