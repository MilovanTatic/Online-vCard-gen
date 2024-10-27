<?php
/**
 * ThreeDSHandler Interface
 *
 * Defines the contract for handling 3D Secure authentication processes.
 *
 * @package NovaBankaIPG\Interfaces
 * @since 1.0.1
 */

namespace NovaBankaIPG\Interfaces;

use WC_Order;

interface ThreeDSHandlerInterface {
	/**
	 * Initiate 3D Secure authentication.
	 *
	 * @param WC_Order $order The order to initiate 3D Secure for.
	 * @param array    $auth_data Authentication data.
	 * @return array Response from the IPG.
	 * @throws NovaBankaIPGException When 3DS initiation fails.
	 */
	public function initiate_3ds( WC_Order $order, array $auth_data ): array;

	/**
	 * Verify 3D Secure authentication.
	 *
	 * @param WC_Order $order The order to verify 3D Secure for.
	 * @param array    $verification_data Verification data.
	 * @return array Response from the IPG.
	 * @throws NovaBankaIPGException When 3DS verification fails.
	 */
	public function verify_3ds( WC_Order $order, array $verification_data ): array;

	/**
	 * Check if 3D Secure is required.
	 *
	 * @param array $transaction_data Transaction data.
	 * @return bool True if 3DS is required.
	 */
	public static function is_3ds_required( array $transaction_data ): bool;

	/**
	 * Generate 3D Secure authentication URL.
	 *
	 * @param array $transaction_data Transaction data.
	 * @return string 3DS authentication URL.
	 * @throws NovaBankaIPGException If URL generation fails.
	 */
	public static function generate_3ds_url( array $transaction_data ): string;

	/**
	 * Verify 3DS authentication response signature.
	 *
	 * @param array  $response_data Response data.
	 * @param string $signature Expected signature.
	 * @return bool True if signature is valid.
	 */
	public static function verify_3ds_signature( array $response_data, string $signature ): bool;
}
