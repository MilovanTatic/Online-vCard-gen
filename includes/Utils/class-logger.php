<?php
/**
 * Logger Utility Class
 *
 * This class is responsible for managing all logging for the NovaBanka IPG plugin.
 * It uses WordPress's built-in WC_Logger to handle different levels of logging based on plugin settings.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Interfaces\LoggerInterface;

/**
 * Logger Class
 *
 * Handles logging operations for the NovaBanka IPG plugin.
 * Implements LoggerInterface for standardized logging operations.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class Logger implements LoggerInterface {
	/**
	 * Logger instance.
	 *
	 * @var \WC_Logger|null
	 */
	private $logger = null;

	/**
	 * Source identifier for logs.
	 *
	 * @var string
	 */
	private $source = 'novabankaipg';

	/**
	 * Constructor for the Logger utility class.
	 */
	public function __construct() {
		add_action( 'woocommerce_init', array( $this, 'init_logger' ) );
	}

	/**
	 * Initialize WC_Logger instance.
	 */
	public function init_logger(): void {
		if ( class_exists( 'WC_Logger' ) && is_null( $this->logger ) ) {
			$this->logger = wc_get_logger();
		}
	}

	/**
	 * Get logger instance.
	 *
	 * @return \WC_Logger|null
	 */
	private function get_logger() {
		if ( is_null( $this->logger ) ) {
			$this->init_logger();
		}
		return $this->logger;
	}

	/**
	 * Log informational messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function info( string $message, array $context = array() ): void {
		if ( Config::is_debug_mode() && $this->get_logger() ) {
			$this->log( 'info', $this->formatMessage( $message ), $this->sanitizeContext( $context ) );
		}
	}

	/**
	 * Log warning messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function warning( string $message, array $context = array() ): void {
		if ( $this->get_logger() ) {
			$this->log( 'warning', $this->formatMessage( $message ), $this->sanitizeContext( $context ) );
		}
	}

	/**
	 * Log error messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function error( string $message, array $context = array() ): void {
		if ( $this->get_logger() ) {
			$this->log( 'error', $this->formatMessage( $message ), $this->sanitizeContext( $context ) );
		}
	}

	/**
	 * Log debug messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( Config::is_debug_mode() && $this->get_logger() ) {
			$this->log( 'debug', $this->formatMessage( $message ), $this->sanitizeContext( $context ) );
		}
	}

	/**
	 * Log critical messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function critical( string $message, array $context = array() ): void {
		if ( $this->get_logger() ) {
			$this->log( 'critical', $this->formatMessage( $message ), $this->sanitizeContext( $context ) );
		}
	}

	/**
	 * Log payment messages.
	 *
	 * @param string $status  The payment status.
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function log_payment( string $status, string $message, array $context = array() ): void {
		if ( $this->get_logger() ) {
			$context['payment_status'] = $status;
			$this->log( 'payment', $this->formatMessage( $message ), $this->sanitizeContext( $context ) );
		}
	}

	/**
	 * Generic log method for handling all log levels.
	 *
	 * @param string $level   The level of the log (info, warning, error, debug).
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		if ( ! $this->get_logger() ) {
			return;
		}

		$log_entry = array(
			'timestamp' => current_time( 'c' ),
			'level'     => strtoupper( $level ),
			'message'   => $message,
			'context'   => $context,
		);

		$this->get_logger()->log(
			$level,
			wp_json_encode( $log_entry, JSON_PRETTY_PRINT ),
			array( 'source' => $this->source )
		);
	}

	/**
	 * Format the log message with a timestamp.
	 *
	 * @param string $message The message to format.
	 * @return string The formatted message with timestamp.
	 */
	private function formatMessage( string $message ): string {
		return sprintf( '[%s] %s', current_time( 'c' ), $message );
	}

	/**
	 * Sanitize the context array by redacting sensitive values.
	 *
	 * @param array $context The context array to sanitize.
	 * @return array The sanitized context array.
	 */
	private function sanitizeContext( array $context ): array {
		$sanitized = array();
		foreach ( $context as $key => $value ) {
			if ( $this->shouldRedactKey( $key ) ) {
				$sanitized[ $key ] = '[REDACTED]';
			} else {
				$sanitized[ $key ] = $value;
			}
		}
		return $sanitized;
	}

	/**
	 * Check if a key should be redacted from logs.
	 *
	 * @param string $key The key to check.
	 * @return bool True if the key should be redacted, false otherwise.
	 */
	private function shouldRedactKey( string $key ): bool {
		$sensitive_keys = array(
			'password',
			'secret',
			'key',
			'token',
			'auth',
			'credential',
		);

		foreach ( $sensitive_keys as $sensitive ) {
			if ( stripos( $key, $sensitive ) !== false ) {
				return true;
			}
		}
		return false;
	}
}
