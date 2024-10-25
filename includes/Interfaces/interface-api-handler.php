<?php
/**
 * Interface for API Handler
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.0
 */

namespace NovaBankaIPG\Interfaces;

interface APIHandlerInterface {
	/**
	 * Send PaymentInit request to IPG
	 *
	 * @param array $data Payment initialization data.
	 * @return array
	 * @throws NovaBankaIPG\Exceptions\NovaBankaIPGException If the payment initialization fails.
	 */
	public function send_payment_init( array $data ): array;

	/**
	 * Verify payment notification
	 *
	 * @param array $notification Notification data.
	 * @return bool
	 * @throws NovaBankaIPG\Exceptions\NovaBankaIPGException If the notification verification fails.
	 */
	public function verify_notification( array $notification ): bool;

	/**
	 * Generate notification response
	 *
	 * @param string $payment_id  Payment ID.
	 * @param string $redirect_url Redirect URL.
	 * @return array
	 */
	public function generate_notification_response( string $payment_id, string $redirect_url ): array;

	/**
	 * Set API configuration
	 *
	 * @param array $config API configuration.
	 * @return void
	 */
	public function set_config( array $config ): void;
}
