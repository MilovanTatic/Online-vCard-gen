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
use NovaBankaIPG\Utils\MessageHandler;
use NovaBankaIPG\Exceptions\NovaBankaIPGException;

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
	 * Message Handler instance.
	 *
	 * @var MessageHandler
	 */
	private $message_handler;

	/**
	 * Data Handler instance.
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Logger instance.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param APIHandler     $api_handler     API Handler instance.
	 * @param MessageHandler $message_handler Message Handler instance.
	 * @param DataHandler    $data_handler    Data Handler instance.
	 * @param Logger         $logger          Logger instance.
	 */
	public function __construct(
		APIHandler $api_handler,
		MessageHandler $message_handler,
		DataHandler $data_handler,
		Logger $logger
	) {
		$this->api_handler     = $api_handler;
		$this->message_handler = $message_handler;
		$this->data_handler    = $data_handler;
		$this->logger          = $logger;
	}

	/**
	 * Initialize payment for an order.
	 *
	 * @param WC_Order $order        The order to process payment for.
	 * @param array    $payment_data Additional payment data.
	 * @return array Payment initialization response.
	 * @throws NovaBankaIPGException When payment initialization fails.
	 */
	public function initialize_payment( WC_Order $order, array $payment_data ): array {
		try {
			// Validate required payment data.
			$required_fields = array( 'terminal_id', 'terminal_password' );
			foreach ( $required_fields as $field ) {
				if ( empty( $payment_data[ $field ] ) ) {
					throw new NovaBankaIPGException(
						sprintf(
							/* translators: %s: field name */
							esc_html__( 'Missing required field: %s', 'novabanka-ipg-gateway' ),
							esc_html( $field )
						)
					);
				}
			}

			// Merge payment data with prepared data.
			$init_data = array_merge(
				$this->prepare_payment_init_data( $order ),
				array(
					'id'       => $payment_data['terminal_id'],
					'password' => $payment_data['terminal_password'],
					'amt'      => $this->data_handler->format_amount( $order->get_total() ),
					'trackid'  => $order->get_order_key(),
				)
			);

			/**
			 * Filter payment data before processing.
			 *
			 * @since 1.0.1
			 * @param array    $init_data The payment initialization data.
			 * @param WC_Order $order     The order being processed.
			 */
			$init_data = apply_filters( 'novabankaipg_payment_init_data', $init_data, $order );

			// Generate message verifier.
			$init_data['msgVerifier'] = $this->message_handler->generate_message_verifier(
				$init_data['msgName'],
				$init_data['version'],
				$init_data['id'],
				$init_data['password'],
				$init_data['amt'],
				$init_data['trackid']
			);

			// Send request to IPG.
			$response = $this->api_handler->send_payment_init_request( $init_data );

			// Log successful initialization.
			$this->logger->info(
				'Payment initialized successfully.',
				array(
					'order_id'   => $order->get_id(),
					'payment_id' => $response['paymentid'] ?? null,
				)
			);

			return $response;

		} catch ( \Exception $e ) {
			$this->logger->error(
				'Payment initialization failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $e->getMessage(),
				)
			);
			throw new NovaBankaIPGException( esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * Prepare payment initialization data.
	 *
	 * @param WC_Order $order The order being processed.
	 * @return array Prepared payment data.
	 */
	private function prepare_payment_init_data( WC_Order $order ): array {
		$udf_fields = $this->prepare_udf_fields( $order );

		$payment_data = array(
			'msgName'      => 'PaymentInitRequest',
			'version'      => '1',
			'id'           => $this->config->get_merchant_id(),
			'password'     => $this->config->get_merchant_password(),
			'action'       => '1', // Standard payment .
			'amt'          => $this->format_amount( $order->get_total() ),
			'currencycode' => $this->get_currency_code( $order ),
			'trackid'      => $order->get_id(),
			'responseURL'  => $this->get_response_url( $order ),
			'errorURL'     => $this->get_error_url( $order ),
			'langid'       => 'EN',
			'recurAction'  => '', // Normal e-commerce order .
		);

		// Merge UDF fields .
		$payment_data = array_merge( $payment_data, $udf_fields );

		// Allow payment data modification through filter .
		return apply_filters( 'novabankaipg_payment_init_data', $payment_data, $order );
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

	/**
	 * Formats user defined fields according to IPG specifications .
	 *
	 * @param WC_Order $order The order object .
	 * @return array Formatted UDF fields .
	 */
	private function prepare_udf_fields( WC_Order $order ): array {
		// Format order items for UDF3 .
		$order_items = array_map(
			function ( $item ) {
				return array(
					'name'     => substr( $item->get_name(), 0, 50 ), // Limit name length .
					'quantity' => $item->get_quantity(),
					'total'    => $this->format_amount( $item->get_total() ),
				);
			},
			$order->get_items()
		);

		return array(
			'udf1' => substr( $order->get_id(), 0, 255 ), // Order ID, limited to 255 chars .
			'udf2' => substr( 'novabankaipg', 0, 255 ), // Gateway identifier, limited to 255 chars .
			'udf3' => substr( wp_json_encode( $order_items ), 0, 255 ), // Order items, limited to 255 chars .
			'udf4' => '', // Reserved for future use .
			'udf5' => '', // Reserved for future use .
		);
	}

	/**
	 * Process the API response from IPG gateway .
	 *
	 * @param array    $response The raw API response .
	 * @param WC_Order $order    The order being processed.
	 * @return array Processed response data.
	 * @throws NovaBankaIPGException If response indicates an error.
	 */
	private function process_api_response( $response, WC_Order $order ): array {
		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				'API request failed',
				array(
					'order_id' => $order->get_id(),
					'error'    => $response->get_error_message(),
				)
			);
			throw new NovaBankaIPGException(
				/* translators: %s: error message */
				esc_html__( 'Connection to payment gateway failed.', 'novabanka-ipg-gateway' )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( empty( $data ) ) {
			$this->logger->error(
				'Invalid JSON response',
				array(
					'order_id' => $order->get_id(),
					'response' => $body,
				)
			);
			throw new NovaBankaIPGException(
				/* translators: %s: error message */
				esc_html__( 'Invalid response from payment gateway.', 'novabanka-ipg-gateway' )
			);
		}
		// Check for IPG error response .
		if ( isset( $data['type'] ) && 'error' === $data['type'] ) {
			$error_message = sprintf(
				/* translators: %1$s: error code, %2$s: error description */
				esc_html__( 'IPG Error: %1$s - %2$s', 'novabanka-ipg-gateway' ),
				$data['errorCode'] ?? 'Unknown',
				$data['errorDesc'] ?? 'Unknown error'
			);

			$this->logger->error(
				'Payment initialization failed.',
				array(
					'order_id' => $order->get_id(),
					'error'    => $error_message,
				)
			);

			// Add order note for admin reference .
			$order->add_order_note(
				sprintf(
					/* translators: %s: error message */
					esc_html__( 'Payment failed: %s', 'novabanka-ipg-gateway' ),
					esc_html( $error_message )
				)
			);

			throw new NovaBankaIPGException( esc_html( $error_message ) );
		}

		// Verify required fields are present .
		if ( empty( $data['browserRedirectionURL'] ) ) {
			$this->logger->error(
				'Missing redirect URL in response',
				array(
					'order_id' => $order->get_id(),
					'response' => $data,
				)
			);
			throw new NovaBankaIPGException(
				/* translators: %s: error message */
				esc_html__( 'Invalid gateway response: Missing redirect URL.', 'novabanka-ipg-gateway' )
			);
		}

		return $data;
	}
}
