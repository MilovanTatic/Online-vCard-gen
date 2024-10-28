<?php
/**
 * Payment Service Class
 *
 * Handles payment processing operations for the NovaBanka IPG plugin.
 * Implements payment flow according to IPG integration guide.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use WC_Order;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\DataHandler;
use NovaBankaIPG\Utils\SharedUtilities;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;
use Exception;

/**
 * Handles payment processing operations.
 */
class PaymentService {
	/**
	 * API Handler instance.
	 *
	 * @var APIHandler
	 */
	private $api_handler;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Data Handler instance.
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Constructor.
	 *
	 * @param APIHandler  $api_handler  API handler instance.
	 * @param Logger      $logger       Logger instance.
	 * @param DataHandler $data_handler Data handler instance.
	 */
	public function __construct(
		APIHandler $api_handler,
		Logger $logger,
		DataHandler $data_handler
	) {
		$this->api_handler  = $api_handler;
		$this->logger       = $logger;
		$this->data_handler = $data_handler;
	}

	/**
	 * Process a payment for an order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array Payment result data.
	 * @throws NovaBankaIPGException When payment processing fails.
	 */
	public function process_order_payment( int $order_id ): array {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new NovaBankaIPGException( esc_html__( 'Invalid order ID.', 'novabanka-ipg-gateway' ) );
		}

		try {
			// Prepare payment data.
			$payment_data = array(
				'order_id' => $order_id,
				'amount'   => $order->get_total(),
				'currency' => $order->get_currency(),
				'trackid'  => $order->get_order_key(),
			);

			// Allow plugins to modify the payment data.
			$payment_data = apply_filters( 'novabankaipg_before_payment_process', $payment_data, $order );

			// Validate required fields according to IPG guide.
			SharedUtilities::validate_required_fields(
				$payment_data,
				array( 'amount', 'currency', 'order_id', 'trackid' )
			);

			// Initialize payment.
			$response = $this->initialize_payment( $order, $payment_data );

			// Update order status.
			$order->update_status(
				'on-hold',
				esc_html__( 'Payment initialized. Awaiting customer redirection.', 'novabanka-ipg-gateway' )
			);

			// Store payment ID for future reference.
			$order->update_meta_data( '_novabankaipg_payment_id', $response['paymentid'] );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $response['browserRedirectionURL'],
			);

		} catch ( Exception $e ) {
			$this->logger->error(
				'Payment processing failed: ' . esc_html( $e->getMessage() ),
				array(
					'order_id' => $order_id,
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Process a refund for an order.
	 *
	 * @param WC_Order $order  The order to refund.
	 * @param float    $amount The amount to refund.
	 * @param string   $reason The reason for the refund.
	 * @return array The response from the IPG.
	 * @throws NovaBankaIPGException When the refund fails.
	 */
	public function process_refund( WC_Order $order, float $amount, string $reason = '' ): array {
		try {
			// Prepare refund data.
			$refund_data = array(
				'order_id'   => $order->get_id(),
				'amount'     => $amount,
				'reason'     => $reason,
				'payment_id' => $order->get_meta( '_novabankaipg_payment_id' ),
			);

			// Allow plugins to modify refund data.
			$refund_data = apply_filters( 'novabankaipg_before_refund_process', $refund_data, $order );

			// Validate required fields.
			SharedUtilities::validate_required_fields(
				$refund_data,
				array( 'order_id', 'amount', 'payment_id' )
			);

			// Send refund request.
			$response = $this->api_handler->send_refund_request( $refund_data );

			/**
			 * Action after refund processing.
			 *
			 * @since 1.0.1
			 * @param array    $response    The API response.
			 * @param WC_Order $order       The order being refunded.
			 * @param float    $amount      The refund amount.
			 * @param string   $reason      The refund reason.
			 */
			do_action( 'novabankaipg_after_refund_process', $response, $order, $amount, $reason );

			// Update order notes.
			$order->add_order_note(
				sprintf(
					/* translators: %1$s: amount, %2$s: reason */
					esc_html__( 'Refund processed successfully. Amount: %1$s, Reason: %2$s', 'novabanka-ipg-gateway' ),
					wc_price( $amount ),
					$reason
				)
			);

			return $response;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Refund failed: ' . esc_html( $e->getMessage() ),
				array(
					'order_id' => $order->get_id(),
					'amount'   => $amount,
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Initialize payment for an order.
	 *
	 * @param WC_Order $order        The order to process payment for.
	 * @param array    $payment_data The payment data.
	 * @return array Payment initialization response.
	 * @throws NovaBankaIPGException When payment initialization fails.
	 */
	private function initialize_payment( WC_Order $order, array $payment_data ): array {
		try {
			// Prepare payment data according to IPG guide.
			$init_data = $this->prepare_payment_init_data( $order, $payment_data );

			/**
			 * Filter payment data before processing.
			 *
			 * @since 1.0.1
			 * @param array    $init_data The payment initialization data.
			 * @param WC_Order $order     The order being processed.
			 */
			$init_data = apply_filters( 'novabankaipg_payment_init_data', $init_data, $order );

			/**
			 * Action before payment initialization.
			 *
			 * @since 1.0.1
			 * @param array    $init_data The payment initialization data.
			 * @param WC_Order $order     The order being processed.
			 */
			do_action( 'novabankaipg_before_payment_init', $init_data, $order );

			// Send payment initialization request.
			$response = $this->api_handler->send_payment_init_request( $init_data );

			/**
			 * Action after payment initialization.
			 *
			 * @since 1.0.1
			 * @param array    $response  The API response.
			 * @param WC_Order $order     The order being processed.
			 * @param array    $init_data The payment initialization data.
			 */
			do_action( 'novabankaipg_after_payment_init', $response, $order, $init_data );

			return $response;

		} catch ( Exception $e ) {
			$this->logger->error(
				'Payment initialization failed: ' . esc_html( $e->getMessage() ),
				array(
					'order_id' => $order->get_id(),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Prepare payment initialization data according to IPG guide.
	 *
	 * @param WC_Order $order        The order to prepare data for.
	 * @param array    $payment_data The base payment data.
	 * @return array Prepared payment initialization data.
	 */
	private function prepare_payment_init_data( WC_Order $order, array $payment_data ): array {
		return array(
			'msgName'     => 'PaymentInitRequest',
			'version'     => '1',
			'amount'      => $this->data_handler->format_amount( $payment_data['amount'] ),
			'currency'    => $this->data_handler->get_currency_code( $payment_data['currency'] ),
			'trackid'     => $payment_data['trackid'],
			'order_id'    => $payment_data['order_id'],
			'responseURL' => $this->get_response_url( $order ),
			'errorURL'    => $this->get_error_url( $order ),
			'langid'      => $this->get_language_code(),
			'email'       => $order->get_billing_email(),
			'udf1'        => $order->get_id(),
			'udf2'        => $order->get_payment_method(),
			'udf3'        => wp_json_encode( $this->get_order_items( $order ) ),
		);
	}

	/**
	 * Get response URL for successful payments.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return string Response URL.
	 */
	private function get_response_url( WC_Order $order ): string {
		return add_query_arg(
			array(
				'wc-api' => 'novabankaipg',
				'order'  => $order->get_id(),
			),
			home_url( '/' )
		);
	}

	/**
	 * Get error URL for failed payments.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return string Error URL.
	 */
	private function get_error_url( WC_Order $order ): string {
		return add_query_arg(
			array(
				'wc-api' => 'novabankaipg-error',
				'order'  => $order->get_id(),
			),
			home_url( '/' )
		);
	}

	/**
	 * Get language code for IPG interface.
	 *
	 * @return string Language code.
	 */
	private function get_language_code(): string {
		$locale = get_locale();
		$lang   = substr( $locale, 0, 2 );
		return strtoupper( $lang );
	}

	/**
	 * Get order items for transaction reference.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return array Order items data.
	 */
	private function get_order_items( WC_Order $order ): array {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
			);
		}
		return $items;
	}
}
