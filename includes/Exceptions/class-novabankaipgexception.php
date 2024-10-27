<?php
/**
 * Custom Exception Handler
 *
 * @package     NovaBankaIPG\Exceptions
 * @since       1.0.0
 */

namespace NovaBankaIPG\Exceptions;

defined( 'ABSPATH' ) || exit;

/**
 * NovaBankaIPGException Class
 *
 * Custom exception class for handling IPG specific errors.
 *
 * @since 1.0.0
 */
class NovaBankaIPGException extends \Exception {
	/**
	 * Error codes and their messages
	 */
	private const ERROR_CODES = array(
		// API Errors.
		'API_ERROR'             => array(
			'code'    => 1000,
			'message' => 'API communication error',
		),
		'INVALID_RESPONSE'      => array(
			'code'    => 1001,
			'message' => 'Invalid response from gateway',
		),
		'INVALID_SIGNATURE'     => array(
			'code'    => 1002,
			'message' => 'Invalid message signature',
		),

		// Validation Errors.
		'INVALID_AMOUNT'        => array(
			'code'    => 2000,
			'message' => 'Invalid amount format or value',
		),
		'INVALID_CURRENCY'      => array(
			'code'    => 2001,
			'message' => 'Unsupported currency',
		),
		'MISSING_FIELD'         => array(
			'code'    => 2002,
			'message' => 'Required field missing',
		),

		// Payment Errors.
		'PAYMENT_FAILED'        => array(
			'code'    => 3000,
			'message' => 'Payment failed',
		),
		'PAYMENT_CANCELLED'     => array(
			'code'    => 3001,
			'message' => 'Payment cancelled by user',
		),
		'3DS_ERROR'             => array(
			'code'    => 3002,
			'message' => '3D Secure authentication failed',
		),

		// Order Errors.
		'ORDER_NOT_FOUND'       => array(
			'code'    => 4000,
			'message' => 'Order not found',
		),
		'INVALID_ORDER_STATE'   => array(
			'code'    => 4001,
			'message' => 'Invalid order state',
		),

		// Configuration Errors.
		'INVALID_CONFIGURATION' => array(
			'code'    => 5000,
			'message' => 'Invalid gateway configuration',
		),
	);

	/**
	 * Additional error data
	 *
	 * @var mixed
	 */
	private $error_data;

	/**
	 * Error type
	 *
	 * @var string
	 */
	private $error_type;

	/**
	 * Constructor
	 *
	 * @param string          $message    Error message.
	 * @param string          $error_type Error type from ERROR_CODES.
	 * @param mixed           $error_data Additional error data.
	 * @param \Throwable|null $previous   Previous exception.
	 */
	public function __construct(
		string $message = '',
		string $error_type = 'API_ERROR',
		$error_data = null,
		\Throwable $previous = null
	) {
		$error_code = self::ERROR_CODES[ $error_type ]['code'] ?? 1000;
		parent::__construct(
			$message ? $message : self::ERROR_CODES[ $error_type ]['message'],
			$error_code,
			$previous
		);
		$this->error_type = $error_type;
		$this->error_data = $error_data;
	}

	/**
	 * Get error data
	 *
	 * @return mixed
	 */
	public function getErrorData() {
		return $this->error_data;
	}

	/**
	 * Get error type
	 *
	 * @return string
	 */
	public function getErrorType(): string {
		return $this->error_type;
	}

	/**
	 * Create API error exception
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 * @return self
	 */
	public static function apiError( string $message = '', $data = null ): self {
		return new self( $message, 'API_ERROR', $data );
	}

	/**
	 * Create validation error exception
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 * @return self
	 */
	public static function validationError( string $message = '', $data = null ): self {
		return new self( $message, 'MISSING_FIELD', $data );
	}

	/**
	 * Create payment error exception
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 * @return self
	 */
	public static function paymentError( string $message = '', $data = null ): self {
		return new self( $message, 'PAYMENT_FAILED', $data );
	}

	/**
	 * Create an invalid signature exception
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Additional error data.
	 * @return self
	 */
	public static function invalidSignature( string $message = '', $data = null ): self {
		return new self( $message, 'INVALID_SIGNATURE', $data );
	}

	/**
	 * Create an order not found exception
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Additional error data.
	 * @return self
	 */
	public static function orderNotFound( string $message = '', $data = null ): self {
		return new self( $message, 'ORDER_NOT_FOUND', $data );
	}

	/**
	 * Create invalid configuration exception
	 *
	 * @param string $message Error message.
	 * @param mixed  $data    Additional error data.
	 * @return self
	 */
	public static function invalidConfiguration( string $message = '', $data = null ): self {
		return new self( $message, 'INVALID_CONFIGURATION', $data );
	}
}
