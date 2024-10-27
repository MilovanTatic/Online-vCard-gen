<?php
/**
 * MessageHandler Interface
 *
 * Defines the contract for handling message construction and verification.
 *
 * @package NovaBankaIPG\Interfaces
 * @since 1.0.1
 */

namespace NovaBankaIPG\Interfaces;

interface MessageHandlerInterface {
	/**
	 * Generate payment initialization request message.
	 *
	 * @param array $data Payment data.
	 * @return array Prepared request message.
	 * @throws NovaBankaIPGException When message generation fails.
	 */
	public function generate_payment_init_request( array $data ): array;

	/**
	 * Generate notification response message.
	 *
	 * @param array  $notification Notification data from IPG.
	 * @param string $redirect_url URL for browser redirection.
	 * @return array Prepared response message.
	 * @throws NovaBankaIPGException When response generation fails.
	 */
	public function generate_notification_response( array $notification, string $redirect_url ): array;

	/**
	 * Prepare payment initialization request data.
	 *
	 * @param array $data Payment initialization data.
	 * @return array Prepared request data.
	 * @throws NovaBankaIPGException When data preparation fails.
	 */
	public function prepare_payment_init_request( array $data ): array;
}
