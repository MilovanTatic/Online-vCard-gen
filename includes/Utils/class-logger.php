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

		$message = sanitize_text_field( $message );
		$context = SharedUtilities::redact_sensitive_data( $context );

		$log_entry = $this->format_message( $message, $context );

		$log_entry = apply_filters( 'novabankaipg_log_entry', $log_entry, $level, $message, $context );

		$this->wc_logger->{$level}(
			$log_entry,
			array(
				'source' => self::LOG_SOURCE,
			)
		);

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
	 * Log error and throw exception.
	 *
	 * @param string $message    Error message.
	 * @param array  $context    Error context.
	 * @param string $error_type Error type for exception.
	 * @throws NovaBankaIPGException
	 */
	public function log_error_and_throw(
		string $message,
		array $context = array(),
		string $error_type = 'API_ERROR'
	): void {
		$this->error( $message, $context );
		throw new NovaBankaIPGException( $message, $error_type );
	}
}
