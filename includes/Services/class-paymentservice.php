<?php
/**
 * PaymentService Class
 *
 * This class is responsible for managing payment-related logic for NovaBanka IPG.
 * It processes payments, manages refunds, and handles payment status updates.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\Config;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use WC_Order;
use Exception;
use NovaBankaIPG\Utils\APIHandlerInterface;
use NovaBankaIPG\Utils\LoggerInterface;

/**
 * Class PaymentService
 *
 * Handles payment processing and refund operations.
 */
class PaymentService {

	/**
	 * API Handler instance.
	 *
	 * @var APIHandlerInterface
	 */
	private $api_handler;

	/**
	 * Logger instance.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Constructor for the PaymentService class.
	 *
	 * @param APIHandlerInterface $api_handler API handler instance.
	 * @param LoggerInterface     $logger      Logger instance.
	 */
	public function __construct(APIHandlerInterface $api_handler, LoggerInterface $logger) {
		$this->api_handler = $api_handler;
		$this->logger      = $logger;
	}

	/**
	 * Process a payment for an order.
	 *
	 * @param WC_Order $order The order to process payment for.
	 * @param array    $payment_data The payment data to be sent to IPG.
	 * @return array The response from the IPG.
	 * @throws NovaBankaIPGException When the payment processing fails.
	 */
	public function process_payment( WC_Order $order, array $payment_data ): array {
		try {
			// Validate required fields.
			SharedUtilities::validate_required_fields(
				$payment_data,
				array(
					'amount',
					'currency',
					'order_id',
				)
			);

			// Log payment request if in debug mode.
			if ( Config::get_setting( 'debug', false ) ) {
				$this->logger->debug( 'Processing payment request', array( 'payment_data' => $payment_data ) );
			}

			// Send the payment request to the IPG.
			$response = $this->api_handler->send_payment_request( $payment_data );

			// Handle the response from IPG.
			if ( 'SUCCESS' === $response['status'] ) {
				$order->payment_complete( $response['transaction_id'] );
				$order->add_order_note(
					sprintf(
						/* translators: %1$s: Transaction ID, %2$s: Amount */
						__( 'Payment processed successfully. Transaction ID: %1$s, Amount: %2$s', 'novabanka-ipg-gateway' ),
						$response['transaction_id'],
						$payment_data['amount']
					)
				);
				$this->logger->info(
					'Payment processed successfully.',
					array(
						'order_id' => $order->get_id(),
						'response' => $response,
					)
				);
			} else {
				throw NovaBankaIPGException::paymentError( 'Payment processing failed.', $response );
			}

			return $response;
		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'Payment processing failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw $e;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Payment processing failed due to an unexpected error.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( 'Payment processing failed: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Refund a payment for an order.
	 *
	 * @param WC_Order $order The order to refund.
	 * @param float    $amount The amount to refund.
	 * @param string   $reason The reason for the refund.
	 * @return array The response from the IPG.
	 * @throws NovaBankaIPGException When the refund fails.
	 */
	public function process_refund( WC_Order $order, float $amount, string $reason = '' ): array {
		try {
			// Validate required fields.
			SharedUtilities::validate_required_fields( array( 'amount' => $amount ), array( 'amount' ) );

			$refund_data = array(
				'order_id' => $order->get_id(),
				'amount'   => $amount,
				'reason'   => $reason,
			);

			// Log refund request if in debug mode.
			if ( Config::get_setting( 'debug', false ) ) {
				$this->logger->debug( 'Processing refund request', array( 'refund_data' => $refund_data ) );
			}

			// Send the refund request to the IPG.
			$response = $this->api_handler->send_refund_request( $refund_data );

			// Handle the response from IPG.
			if ( 'SUCCESS' === $response['status'] ) {
				$order->add_order_note(
					sprintf(
						/* translators: %1$s: Refund amount, %2$s: Refund reason */
						__( 'Refund processed successfully. Refund Amount: %1$s, Reason: %2$s', 'novabanka-ipg-gateway' ),
						$amount,
						$reason
					)
				);
				$this->logger->info(
					'Refund processed successfully.',
					array(
						'order_id' => $order->get_id(),
						'response' => $response,
					)
				);
			} else {
				throw NovaBankaIPGException::paymentError( 'Refund processing failed.', $response );
			}

			return $response;
		} catch ( NovaBankaIPGException $e ) {
			$this->logger->error(
				'Refund processing failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw $e;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Refund processing failed due to an unexpected error.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( 'Refund processing failed: ' . esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Parse payment query response.
	 *
	 * @param array $response Response from gateway.
	 * @return array Processed response data.
	 * @throws NovaBankaIPGException If response processing fails due to invalid input or processing error.
	 */
	public function parse_payment_query_response( array $response ): array {
		try {
			// Validate required fields.
			SharedUtilities::validate_required_fields(
				$response,
				array(
					'msgName',
					'version',
					'msgDateTime',
					'paymentid',
					'trackid',
					'status',
					'result',
					'amt',
					'msgVerifier',
				)
			);

			// Parse transaction rows if present.
			$transactions = array();
			if ( ! empty( $response['rows'] ) ) {
				$transactions = SharedUtilities::parse_transaction_rows( $response['rows'] );
			}

			// Construct parsed response.
			$parsed_response = array(
				'payment_id'   => $response['paymentid'],
				'track_id'     => $response['trackid'],
				'status'       => SharedUtilities::get_status_description( $response['status'] ),
				'result'       => $response['result'],
				'amount'       => $response['amt'],
				'currency'     => $response['currencycode'] ?? null,
				'transactions' => $transactions,
			);

			return $parsed_response;
		} catch ( Exception $e ) {
			$this->logger->error(
				'Payment Query response processing failed',
				array(
					'error'    => esc_html( $e->getMessage() ),
					'response' => wp_json_encode( $response ),
				)
			);
			throw new NovaBankaIPGException( 
				esc_html( $e->getMessage() ), 
				'QUERY_RESPONSE_ERROR', 
				wp_json_encode( $response )
			);
		}
	}
}
