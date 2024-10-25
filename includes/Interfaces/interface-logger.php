<?php
/**
 * Logger Interface
 *
 * @package     NovaBankaIPG\Interfaces
 * @since       1.0.0
 */

namespace NovaBankaIPG\Interfaces;

defined( 'ABSPATH' ) || exit;

/**
 * Logger Interface
 *
 * @since 1.0.0
 */
interface Logger {
	/**
	 * Log debug message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void;

	/**
	 * Log info message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void;

	/**
	 * Log warning message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void;

	/**
	 * Log error message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void;

	/**
	 * Log critical message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void;

	/**
	 * Log payment process
	 *
	 * @param string $status  Payment status.
	 * @param string $message Status message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function log_payment( string $status, string $message, array $context = array() ): void;
}
