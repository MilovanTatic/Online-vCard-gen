<?php
/**
 * PaymentService Interface
 *
 * Defines the contract for payment processing operations.
 *
 * @package NovaBankaIPG\Interfaces
 * @since 1.0.1
 */

namespace NovaBankaIPG\Interfaces;

use WC_Order;

interface PaymentServiceInterface {
    /**
     * Process a payment for an order.
     *
     * @param WC_Order $order The order to process payment for.
     * @param array    $payment_data The payment data to be sent to IPG.
     * @return array The response from the IPG.
     * @throws NovaBankaIPGException When the payment processing fails.
     */
    public function process_payment(WC_Order $order, array $payment_data): array;

    /**
     * Process a refund for an order.
     *
     * @param WC_Order $order The order to refund.
     * @param float    $amount The amount to refund.
     * @param string   $reason The reason for the refund.
     * @return array The response from the IPG.
     * @throws NovaBankaIPGException When the refund fails.
     */
    public function process_refund(WC_Order $order, float $amount, string $reason = ''): array;

    /**
     * Parse payment query response.
     *
     * @param array $response Response from gateway.
     * @return array Processed response data.
     * @throws NovaBankaIPGException If response processing fails.
     */
    public function parse_payment_query_response(array $response): array;
}
