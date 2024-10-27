<?php
/**
 * Logger Utility Class
 *
 * This class is responsible for logging activities within the NovaBanka IPG plugin.
 * It provides a consistent way to record important events, errors, and warnings.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */

namespace NovaBankaIPG\Utils;

use WC_Logger;
use WC_Log_Levels;

/**
 * Class Logger
 *
 * Handles logging functionality for the NovaBanka IPG plugin.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.1
 */
class Logger {
	/**
	 * WooCommerce Logger instance.
	 *
	 * @var WC_Logger
	 */
	private $wc_logger;

	/**
	 * Log context name.
	 *
	 * @var string
	 */
	private $context;

	/**
	 * Constructor for the Logger class.
	 *
	 * @param string $context The context for the log (e.g., "novabankaipg").
	 */
	public function __construct( $context = 'novabankaipg' ) {
		$this->wc_logger = wc_get_logger();
		$this->context   = $context;
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $message The log message.
	 * @param array  $data Optional additional data to log.
	 */
	public function info( $message, array $data = array() ) {
		$this->log( WC_Log_Levels::INFO, $message, $data );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string $message The log message.
	 * @param array  $data Optional additional data to log.
	 */
	public function warning( $message, array $data = array() ) {
		$this->log( WC_Log_Levels::WARNING, $message, $data );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message The log message.
	 * @param array  $data Optional additional data to log.
	 */
	public function error( $message, array $data = array() ) {
		$this->log( WC_Log_Levels::ERROR, $message, $data );
	}

	/**
	 * Generic log method.
	 *
	 * @param string $level Log level (e.g., info, warning, error).
	 * @param string $message The log message.
	 * @param array  $data Optional additional data to log.
	 */
	private function log( $level, $message, array $data = array() ) {
		$contextual_message = $message;

		// Append data to message if available.
		if ( ! empty( $data ) ) {
			$contextual_message .= ' ' . wp_json_encode( $data );
		}

		// Log the message with context and level.
		$this->wc_logger->log( $level, $contextual_message, array( 'source' => $this->context ) );
	}
}
