<?php
/**
 * NotificationService Interface
 *
 * Defines the contract for handling payment notifications.
 *
 * @package NovaBankaIPG\Interfaces
 * @since 1.0.1
 */

namespace NovaBankaIPG\Interfaces;

use WC_Order;

interface NotificationServiceInterface {
    /**
     * Handle incoming notification from IPG.
     *
     * @param array $notification_data The notification data received from IPG.
     * @return void
     * @throws NovaBankaIPGException When the notification handling fails.
     */
    public function handle_notification(array $notification_data): void;

    /**
     * Process successful payment notification.
     *
     * @param WC_Order $order Order object.
     * @param array    $notification_data Payment notification data.
     * @return void
     */
    public function process_successful_payment(WC_Order $order, array $notification_data): void;

    /**
     * Process failed payment notification.
     *
     * @param WC_Order $order Order object.
     * @param array    $notification_data Payment notification data.
     * @return void
     */
    public function process_failed_payment(WC_Order $order, array $notification_data): void;
}
