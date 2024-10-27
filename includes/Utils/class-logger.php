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

use WC_Logger;
use NovaBankaIPG\Interfaces\Logger as LoggerInterface;

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
	 * @var WC_Logger
	 */
	private $logger;

	/**
	 * Constructor for the Logger utility class.
	 */
	public function __construct() {
		$this->logger = new WC_Logger();
	}

	/**
	 * Log informational messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function info( string $message, array $context = array() ): void {
		if ( Config::is_debug_mode() ) {
			$this->log( 'info', $message, $context );
		}
	}

	/**
	 * Log warning messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log error messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log debug messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( Config::is_debug_mode() ) {
			$this->log( 'debug', $message, $context );
		}
	}

	/**
	 * Log critical messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->log( 'critical', $message, $context );
	}

	/**
	 * Log payment messages.
	 *
	 * @param string $status The payment status.
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function log_payment( string $status, string $message, array $context = array() ): void {
		$context['payment_status'] = $status;
		$this->log( 'payment', $message, $context );
	}

	/**
	 * Generic log method for handling all log levels.
	 *
	 * @param string $level The level of the log (info, warning, error, debug).
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	private function log( string $level, string $message, array $context = array() ): void {
		$context_string = empty( $context ) ? '' : json_encode( $context );
		$this->logger->log( $level, sprintf( '[%s] %s %s', strtoupper( $level ), $message, $context_string ) );
	}
}
