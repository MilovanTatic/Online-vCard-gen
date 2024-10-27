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

class Logger {
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
	public function info( $message, array $context = array() ) {
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
	public function warning( $message, array $context = array() ) {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log error messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function error( $message, array $context = array() ) {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log debug messages.
	 *
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	public function debug( $message, array $context = array() ) {
		if ( Config::is_debug_mode() ) {
			$this->log( 'debug', $message, $context );
		}
	}

	/**
	 * Generic log method for handling all log levels.
	 *
	 * @param string $level The level of the log (info, warning, error, debug).
	 * @param string $message The message to log.
	 * @param array  $context Additional context for the message.
	 */
	private function log( $level, $message, array $context = array() ) {
		$context_string = empty( $context ) ? '' : json_encode( $context );
		$this->logger->log( $level, sprintf( '[%s] %s %s', strtoupper( $level ), $message, $context_string ) );
	}
}
