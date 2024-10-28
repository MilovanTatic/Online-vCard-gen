<?php
/**
 * Custom Exception Handler
 *
 * Handles custom exceptions for the NovaBanka IPG plugin with proper error codes,
 * messages, and data handling.
 *
 * @package NovaBankaIPG\Exceptions
 * @since 1.0.1
 */

namespace NovaBankaIPG\Exceptions;

use Exception;

/**
 * NovaBankaIPGException Class
 *
 * Custom exception class for handling IPG specific errors.
 */
class NovaBankaIPGException extends Exception {
	/**
	 * Error codes and their messages.
	 *
	 * @var array
	 */
	private const ERROR_CODES = array(
		// API Errors (1000-1999).
		'API_ERROR'             => array(
			'code'    => 1000,
			'message' => 'API communication error.',
		),
		'INVALID_RESPONSE'      => array(
			'code'    => 1001,
			'message' => 'Invalid response from gateway.',
		),
		'INVALID_SIGNATURE'     => array(
			'code'    => 1002,
			'message' => 'Invalid message signature.',
		),

		// Validation Errors (2000-2999).
		'INVALID_AMOUNT'        => array(
			'code'    => 2000,
			'message' => 'Invalid amount format or value.',
		),
		'INVALID_CURRENCY'      => array(
			'code'    => 2001,
			'message' => 'Unsupported currency.',
		),
		'MISSING_FIELD'         => array(
			'code'    => 2002,
			'message' => 'Required field missing.',
		),

		// Payment Errors (3000-3999).
		'PAYMENT_FAILED'        => array(
			'code'    => 3000,
			'message' => 'Payment failed.',
		),
		'PAYMENT_CANCELLED'     => array(
			'code'    => 3001,
			'message' => 'Payment cancelled by user.',
		),
		'3DS_ERROR'             => array(
			'code'    => 3002,
			'message' => '3D Secure authentication failed.',
		),

		// Order Errors (4000-4999).
		'ORDER_NOT_FOUND'       => array(
			'code'    => 4000,
			'message' => 'Order not found.',
		),
		'INVALID_ORDER_STATE'   => array(
			'code'    => 4001,
			'message' => 'Invalid order state.',
		),

		// Configuration Errors (5000-5999).
		'INVALID_CONFIGURATION' => array(
			'code'    => 5000,
			'message' => 'Invalid gateway configuration.',
		),
	);

	/**
	 * Additional error data.
	 *
	 * @var mixed
	 */
	private $error_data;

	/**
	 * Error type.
	 *
	 * @var string
	 */
	private $error_type;

	/**
	 * Constructor.
	 *
	 * @param string    $message    Error message.
	 * @param string    $error_type Error type from ERROR_CODES.
	 * @param mixed     $error_data Additional error data.
	 * @param Exception $previous   Previous exception.
	 */
	public function __construct(
		string $message = '',
		string $error_type = 'API_ERROR',
		mixed $error_data = null,
		Exception $previous = null
	) {
		$error_code      = self::ERROR_CODES[ $error_type ]['code'] ?? 1000;
		$default_message = self::ERROR_CODES[ $error_type ]['message'] ?? 'Unknown error.';

		parent::__construct(
			$message ? esc_html( $message ) : esc_html( $default_message ),
			$error_code,
			$previous
		);

		$this->error_type = $error_type;
		$this->error_data = $error_data;
	}

	/**
	 * Get error data.
	 *
	 * @return mixed Error data.
	 */
	public function get_error_data() {
		return $this->error_data;
	}

	/**
	 * Get error type.
	 *
	 * @return string Error type.
	 */
	public function get_error_type(): string {
		return $this->error_type;
	}

	/**
	 * Create API error exception.
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 * @return self
	 */
	public static function api_error( string $message = '', $data = null ): self {
		return new self( $message, 'API_ERROR', $data );
	}

	/**
	 * Create validation error exception.
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 * @return self
	 */
	public static function validation_error( string $message = '', $data = null ): self {
		return new self( $message, 'MISSING_FIELD', $data );
	}

	/**
	 * Create payment error exception.
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 * @return self
	 */
	public static function payment_error( string $message = '', $data = null ): self {
		return new self( $message, 'PAYMENT_FAILED', $data );
	}

	/**
	 * Create invalid signature exception.
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Additional error data.
	 * @return self
	 */
	public static function invalid_signature( string $message = '', $data = null ): self {
		return new self( $message, 'INVALID_SIGNATURE', $data );
	}

	/**
	 * Create order not found exception.
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Additional error data.
	 * @return self
	 */
	public static function order_not_found( string $message = '', $data = null ): self {
		return new self( $message, 'ORDER_NOT_FOUND', $data );
	}

	/**
	 * Create invalid configuration exception.
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Additional error data.
	 * @return self
	 */
	public static function invalid_configuration( string $message = '', $data = null ): self {
		return new self( $message, 'INVALID_CONFIGURATION', $data );
	}

	/**
	 * Get error details as array.
	 *
	 * @return array Error details.
	 */
	public function get_error_details(): array {
		return array(
			'type'    => $this->error_type,
			'code'    => $this->getCode(),
			'message' => $this->getMessage(),
			'data'    => $this->error_data,
		);
	}

	/**
	 * Get error message with code.
	 *
	 * @return string Formatted error message.
	 */
	public function get_error_message(): string {
		return sprintf(
			/* translators: 1: Error code, 2: Error message */
			esc_html__( '[Error %1$d] %2$s', 'novabanka-ipg-gateway' ),
			$this->getCode(),
			$this->getMessage()
		);
	}
}
