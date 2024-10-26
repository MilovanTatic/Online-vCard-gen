<?php
/**
 * Logger Implementation
 *
 * @package     NovaBankaIPG\Utils
 * @since       1.0.0
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Interfaces\Logger as LoggerInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Logger class for handling logging operations.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.0
 */
class Logger implements LoggerInterface {
	/**
	 * WooCommerce Logger instance.
	 *
	 * @var \WC_Logger
	 */
	/**
	 * WooCommerce Logger instance.
	 *
	 * @var \WC_Logger
	 */
	private $logger;

	/**
	 * Log source identifier.
	 *
	 * @var string
	 */
	private $source;

	/**
	 * Whether debug mode is enabled
	 *
	 * @var bool
	 */
	private $debug_mode;

	/**
	 * Constructor
	 *
	 * @param string $source     Log source identifier.
	 * @param bool   $debug_mode Whether debug mode is enabled.
	 */
	public function __construct( string $source = 'novabankaipg', bool $debug_mode = false ) {
		$this->source     = $source;
		$this->debug_mode = $debug_mode;
		$this->logger     = wc_get_logger();
	}

	/**
	 * Log debug message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( ! $this->debug_mode ) {
			return;
		}

		$this->log( 'debug', $this->formatMessage( $message, $context ) );
	}

	/**
	 * Log error message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $this->formatMessage( $message, $context ) );
	}

	/**
	 * Log info message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $this->formatMessage( $message, $context ) );
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $this->formatMessage( $message, $context ) );
	}

	/**
	 * Log critical message
	 *
	 * @param string $message Message to log.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function critical( string $message, array $context = array() ): void {
		$this->log( 'critical', $this->formatMessage( $message, $context ) );
	}

	/**
	 * Log payment process
	 *
	 * @param string $status  Payment status.
	 * @param string $message Status message.
	 * @param array  $context Additional context.
	 * @return void
	 */
	public function log_payment( string $status, string $message, array $context = array() ): void {
		$context['payment_status'] = $status;

		if ( 'error' === $status ) {
			$this->error( $message, $context );
		} else {
			$this->info( $message, $context );
		}
	}

	/**
	 * Log payment process events
	 *
	 * @param string $process Process identifier (A or B).
	 * @param string $event Event description.
	 * @param array  $data Event data.
	 */
	public function log_payment_process(string $process, string $event, array $data = []): void {
		$context = [
			'process' => $process,
			'event'   => $event,
			'data'    => $this->mask_sensitive_data($data)
		];

		$this->debug(sprintf('Payment Process %s: %s', $process, $event), $context);
	}

	/**
	 * Log IPG notification
	 *
	 * @param array $notification Notification data.
	 */
	public function log_notification(array $notification): void {
		$masked_data = $this->mask_sensitive_data($notification);
		$this->debug('IPG notification received', ['notification' => $masked_data]);
	}

	/**
	 * Format log message with context
	 *
	 * @param string $message Message to format.
	 * @param array  $context Context data.
	 * @return string
	 */
	private function formatMessage( string $message, array $context = array() ): string {
		$context = $this->sanitizeContext( $context );

		if ( ! empty( $context ) ) {
			$message .= ' | Context: ' . wp_json_encode( $context );
		}

		return $message;
	}

	/**
	 * Write to log
	 *
	 * @param string $level   Log level.
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( string $level, string $message ): void {
		$this->logger->log(
			$level,
			$message,
			array(
				'source'    => $this->source,
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Sanitize sensitive data in context
	 *
	 * @param array $context Context data.
	 * @return array
	 */
	private function sanitizeContext( array $context ): array {
		$sensitive_fields = array(
			'password',
			'terminal_password',
			'secret_key',
			'card',
			'cvv',
			'cvv2',
			'pan',
		);

		array_walk_recursive(
			$context,
			function ( &$value, $key ) use ( $sensitive_fields ) {
				if ( in_array( strtolower( $key ), $sensitive_fields, true ) ) {
					$value = '***REDACTED***';
				}
			}
		);

		return $context;
	}

	/**
	 * Mask sensitive data in logs
	 *
	 * @param array $data Data to mask.
	 * @return array
	 */
	private function mask_sensitive_data(array $data): array {
		$sensitive_fields = [
			'password',
			'terminal_password',
			'secret_key',
			'msgVerifier'
		];

		foreach ($data as $key => &$value) {
			if (in_array($key, $sensitive_fields, true)) {
				$value = '***MASKED***';
			}
		}

		return $data;
	}

	/**
	 * Set debug mode
	 *
	 * @param bool $debug_mode Whether debug mode is enabled.
	 * @return void
	 */
	public function setDebugMode( bool $debug_mode ): void {
		$this->debug_mode = $debug_mode;
	}

	/**
	 * Log API communication
	 *
	 * @param string $direction Request/Response indicator.
	 * @param string $endpoint  API endpoint.
	 * @param array  $data      Request/Response data.
	 * @return void
	 */
	public function logAPI( string $direction, string $endpoint, array $data ): void {
		$message = sprintf(
			'API %s | Endpoint: %s',
			$direction,
			$endpoint
		);

		$this->debug( $message, $data );
	}

	/**
	 * Log IPG API request and response
	 */
	public function log_api_communication($direction, $endpoint, $data) {
		$masked_data = $this->mask_sensitive_data($data);
		
		$context = [
			'timestamp' => current_time('mysql'),
			'endpoint' => $endpoint,
			'data' => $masked_data,
			'headers' => $this->get_request_headers()
		];

		$this->debug(
			sprintf('IPG %s | Endpoint: %s', $direction, $endpoint),
			$context
		);
	}

	/**
	 * Get request headers safely
	 */
	private function get_request_headers() {
		$headers = [];
		foreach ($_SERVER as $key => $value) {
			if (strpos($key, 'HTTP_') === 0) {
				$header = str_replace('HTTP_', '', $key);
				$header = str_replace('_', '-', strtolower($header));
				$headers[$header] = $value;
			}
		}
		return $headers;
	}
}
