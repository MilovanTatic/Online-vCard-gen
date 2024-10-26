# WooCommerce Asynchronous Payment Flow - NovaBanka IPG

## Overview
The payment flow involves multiple steps across different domains with asynchronous communication between IPG and WooCommerce. This guide provides a step-by-step explanation of the payment processes involved, helping developers understand how to handle payments reliably and efficiently.

## Process Overview

```plaintext
Process A (Customer Journey):
WooCheckout -> Init Payment -> HPP Redirect -> Customer at IPG -> Customer Return to Success/Failure URL

Process B (Server Communication):
IPG Server -> Notification Callback -> Order Status Update -> Response to IPG

These processes are independent and can happen in any order!
```

## Flow Diagram
The overall flow of the payment process can be visualized as follows:

| Customer  | Our Site       | IPG              | Bank         |
|-----------|----------------|------------------|--------------|
| (1) Place Order       |                    |              |
|                   | (2) PaymentInit      |              |
|                   | <--- (3) HPP URL ---- |              |
| <--- (4) Redirect to HPP |                  |              |
|                   |                    | (5) Enter Card Details   |
|                   |                    | --- (6) 3DS Auth ------> |
|                   |                    | <--- (7) Auth Response --|
|                   | <--- (8) Payment Notification |              |
|                   | (9) Process Order   |              |
| <--- (10) Redirect to Result |              |

## Communication Steps
### 1. Initial Order Creation
- Customer places an order on WooCommerce site.
- Order status: **pending**.

### 2. Payment Initialization
- Prepare data for payment initialization with IPG.
  ```php
  // Send to IPG
  $payment_data = [
      // URL to receive asynchronous payment notifications from IPG
      'response_url' => WC()->api_request_url('novabankaipg'),
      
      // URL to redirect the customer upon successful payment
      'success_url' => $success_url,
      
      // URL to redirect the customer upon payment failure
      'failure_url' => $failure_url
  ];
  ```
- Order status: **on-hold**.- Order status: **on-hold**.

### 3. HPP Redirection
- IPG returns HPP URL, and customer is redirected to IPG domain.
- Order status remains **on-hold**.

### 4. Payment Processing
- Customer completes the payment process on the IPG.
- IPG processes the payment with the bank.
- Order status remains unchanged.

### 5. Asynchronous Notification Handling
- IPG sends a notification to WooCommerce using a dedicated endpoint.
  ```php
  // IPG calls our notification URL
  add_action('woocommerce_api_novabankaipg', array($this, 'handle_gateway_response'));
  ```

### 6. Order Update
- The notification from IPG triggers order updates and stores the transaction data.

### 7. Customer Return Handling
- After payment, handle customer redirection based on success or failure:
  - **Success Case**: Redirect the customer to the thank you page, confirming successful payment.
  - **Failure Case**: Redirect the customer to the payment page, displaying an error message and allowing them to retry the payment.
  ```php
  add_action('woocommerce_api_novabankaipg_success', array($this, 'handle_success_return'));
  add_action('woocommerce_api_novabankaipg_failure', array($this, 'handle_failure_return'));
  ```

## API Endpoints
### 1. Notification Endpoint
- **Notification Processing**: Updates the WooCommerce order with details received from IPG.
  ```php
  // /wc-api/novabankaipg
  public function handle_gateway_response() {
      // Process IPG notification
      // Update order status
      // Return response to IPG
  }
  ```

### 2. Success Return Endpoint
- **Success Handling**: Redirects to a thank you page upon payment success.
  ```php
  // /wc-api/novabankaipg_success
  public function handle_success_return() {
      // Verify order
      // Redirect to thank you page
  }
  ```

### 3. Failure Return Endpoint
- **Failure Handling**: Redirects to the payment page with an error message upon failure.
  ```php
  // /wc-api/novabankaipg_failure
  public function handle_failure_return() {
      // Verify order
      // Show error message
      // Redirect to payment page
  }
  ```

## Implementation
```php
class WC_Gateway_NovaBanka_IPG extends WC_Payment_Gateway {
    /**
     * Initial payment setup - starts the customer journey
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        try {
            // 1. Prepare callback URLs for later use
            $urls = [
                'responseURL' => WC()->api_request_url('novabankaipg'),
                'successURL' => add_query_arg([
                    'wc-api' => 'novabankaipg_success',
                    'order-key' => $order->get_order_key()
                ], home_url('/')),
                'errorURL' => add_query_arg([
                    'wc-api' => 'novabankaipg_failure',
                    'order-key' => $order->get_order_key()
                ], home_url('/'))
            ];

            // 2. Initialize payment with IPG
            $payment_data = array_merge($urls, [
                'trackid' => $order->get_id(),
                'amount' => $order->get_total(),
                'currency' => $order->get_currency(),
                // ... other payment data ...
            ]);

            $response = $this->api_handler->initialize_payment($payment_data);

            // 3. Store payment ID and mark order as pending HPP
            $order->update_meta_data('_novabankaipg_payment_id', $response['paymentid']);
            $order->update_status('pending', __('Awaiting HPP redirect', 'novabanka-ipg-gateway'));
            $order->save();

            // 4. Send customer to HPP
            return [
                'result' => 'success',
                'redirect' => $response['browserRedirectionURL']
            ];

        } catch (Exception $e) {
            wc_add_notice($e->getMessage(), 'error');
            return;
        }
    }

    /**
     * IPG Notification Handler - Process B
     * This is completely independent of customer's browser session
     */
    public function handle_ipg_notification() {
        try {
            // 1. Get and validate notification
            $notification = json_decode(file_get_contents('php://input'), true);
            
            if (!$this->api_handler->verify_signature($notification)) {
                throw new Exception('Invalid signature');
            }

            // 2. Get and validate order
            $order = wc_get_order($notification['trackid']);
            if (!$order) {
                throw new Exception('Order not found');
            }

            // 3. Store transaction data regardless of outcome
            $this->store_transaction_data($order, $notification);

            // 4. Process payment result
            if ($notification['result'] === 'CAPTURED') {
                $order->payment_complete($notification['tranid']);
                $order->add_order_note(__('Payment successful - IPG notification received', 'novabanka-ipg-gateway'));
            } else {
                $order->update_status('failed', sprintf(
                    __('Payment failed - IPG notification. Result: %s', 'novabanka-ipg-gateway'),
                    $notification['result']
                ));
            }

            // 5. Acknowledge to IPG
            wp_send_json(['status' => 'OK'], 200);

        } catch (Exception $e) {
            // Log error but don't expose details to IPG
            $this->logger->error('IPG notification failed', [
                'error' => $e->getMessage(),
                'notification' => $notification ?? null
            ]);
            wp_send_json(['status' => 'ERROR'], 500);
        }
    }

    /**
     * Customer Return Handler - Process A
     * Customer returns here after HPP, but order might already be updated by Process B
     */
    public function handle_customer_return_success() {
        $order_key = wc_clean($_GET['order-key'] ?? '');
        $order = wc_get_orders(['order_key' => $order_key, 'limit' => 1])[0] ?? null;

        if (!$order) {
            wc_add_notice(__('Invalid order.', 'novabanka-ipg-gateway'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }

        // Check if IPG notification was already processed (Process B)
        $transaction_id = $order->get_meta('_novabankaipg_transaction_id');
        
        if ($transaction_id) {
            // Order already updated by IPG notification
            if ($order->is_paid()) {
                wp_redirect($this->get_return_url($order));
            } else {
                wc_add_notice(
                    __('Payment was not successful. Please try again.', 'novabanka-ipg-gateway'),
                    'error'
                );
                wp_redirect($order->get_checkout_payment_url());
            }
        } else {
            // IPG notification hasn't arrived yet
            wc_add_notice(
                __('Your payment is being processed. Please wait a moment.', 'novabanka-ipg-gateway'),
                'notice'
            );
            wp_redirect($this->get_return_url($order));
        }
        exit;
    }

    /**
     * Store transaction data from IPG notification
     */
    private function store_transaction_data($order, $notification) {
        // Store all relevant fields
        $fields_to_store = [
            'paymentid', 'tranid', 'auth', 'result', 'track_id', 
            'cardtype', 'cardLastFourDigits'
        ];

        foreach ($fields_to_store as $field) {
            if (isset($notification[$field])) {
                $order->update_meta_data("_novabankaipg_{$field}", $notification[$field]);
            }
        }

        // Store full response for debugging
        $order->update_meta_data('_novabankaipg_raw_response', wp_json_encode($notification));
        
        // Store processing timestamp
        $order->update_meta_data('_novabankaipg_processed_at', time());
        
        $order->save();
    }
}
```

## Order Status Flow
The order status flow is crucial in tracking the progression of a payment through the different stages.

```
pending -> on-hold -> processing/failed -> completed
```

- **pending**: Initial order creation.
- **on-hold**: During HPP/3DS process.
- **processing**: Successful payment.
- **failed**: Failed payment.
- **completed**: Order fulfilled.

## Security Considerations
1. **URL Validation**
   ```php
   // Verify order key
   $order_key = wc_clean($_GET['order-key'] ?? '');
   $order = wc_get_orders(['order_key' => $order_key, 'limit' => 1])[0] ?? null;
   ```

2. **Notification Verification**
   - Since the IPG notification always comes from the domain `novabanka.com`, we can simply validate the source domain. Redirects from the HPP should not be verified as they may vary and could use different URLs.
   ```php
   // Verify the source of the request
   if (strpos($_SERVER['HTTP_REFERER'], 'novabanka.com') === false) {
       throw new Exception('Invalid notification source');
   }
   ```

3. **Order State Validation**
   - Ensure that the current order state is validated before processing to avoid duplicate payments and errors.

## Implementation Notes
1. **URL Generation**
   ```php
   private function get_gateway_urls($order) {
       return [
           'notification_url' => WC()->api_request_url('novabankaipg'),
           'success_url' => add_query_arg([
               'wc-api' => 'novabankaipg_success',
               'order-key' => $order->get_order_key()
           ], home_url('/')),
           'failure_url' => add_query_arg([
               'wc-api' => 'novabankaipg_failure',
               'order-key' => $order->get_order_key()
           ], home_url('/'))
       ];
   }
   ```

2. **Data Storage**
   ```php
   // Store transaction data
   $order->update_meta_data('_novabankaipg_payment_id', $payment_id);
   $order->update_meta_data('_novabankaipg_transaction_id', $transaction_id);
   ```

3. **Error Handling**
   ```php
   try {
       // Process notification
   } catch (Exception $e) {
       $this->logger->error('Payment notification error: ' . $e->getMessage());
       wp_die($e->getMessage(), 'Payment Error', array('response' => 500));
   }
   ```

## Testing Considerations
1. **Notification Testing**
   - Test with the IPG test environment to verify all notification scenarios and transitions in order status.

2. **Customer Return Testing**
   - Verify redirection logic for both success and failure returns.
   - Check for consistent order status displays and ensure proper messages are shown to users.
   - Test scenarios where the customer returns before or after the notification is received.

3. **Security Testing**
   - Test invalid signatures and incorrect order keys to ensure notifications are not accepted if they come from unauthorized sources.
   - Ensure that the system handles duplicate notifications gracefully without causing repeated processing.

4. **Performance Testing**
   - Simulate high transaction volumes to test the system’s performance and the IPG's ability to handle multiple callbacks concurrently.
   - Monitor server resource utilization and response times during these simulations to identify potential bottlenecks.

## Debugging
1. **Enable Extensive Logging During Development**
   - During the development phase, enable detailed debugging logs for every method, function, callback, and key process. This will help track the entire flow and identify issues at any stage.
   
   ```php
   // Log entry into the process_payment function
   $this->logger->debug('Entering process_payment method', [
       'order_id' => $order_id
   ]);

   // Log payment data preparation
   $this->logger->debug('Payment data prepared for IPG initialization', [
       'payment_data' => $payment_data
   ]);

   // Log notification receipt
   $this->logger->debug('IPG Notification received', [
       'notification' => $notification
   ]);

   // Log order status updates
   $this->logger->debug('Updating order status', [
       'order_id' => $order->get_id(),
       'new_status' => $notification['result'] === 'CAPTURED' ? 'processing' : 'failed'
   ]);
   ```

   - Ensure logs are added to every entry and exit point of key methods and when significant state changes occur (e.g., order status updates, data storage).

2. **Monitor Endpoints**
   - Regularly check WooCommerce logs and monitor callbacks to ensure notifications are being received and processed as expected.
   - Log any unexpected errors or anomalies, especially during high transaction periods.

3. **Verify Data Consistency**
   - Validate order metadata, transaction IDs, and amounts to ensure data accuracy.
   - Check for data integrity issues, especially when multiple notifications are received or when race conditions occur.

4. **Error Recovery**
   - Implement retry logic for failed notifications where possible.
   - Ensure that error messages are logged with sufficient detail to aid in troubleshooting while avoiding exposing sensitive information.

## Data Storage Strategy

WooCommerce provides a variety of out-of-the-box (OoTB) functionalities for data management, including order metadata storage and transaction handling. To make the most of these features while keeping the implementation clean and efficient, consider the following suggestions:

1. **Use Built-in WooCommerce Meta Methods Efficiently**
   - Instead of manually storing every piece of data in custom meta fields, use WooCommerce's built-in methods such as `$order->set_transaction_id()` for storing transaction-specific information. This keeps the data standardized and makes it more accessible for other WooCommerce features.
   
   ```php
   // Example: Setting transaction ID
   $order->set_transaction_id($notification['tranid']);
   $order->save();
   ```

2. **Avoid Redundant Metadata**
   - Instead of creating custom meta fields for commonly used data (like `paymentid` or `transaction_id`), consider if that information can be stored using standard WooCommerce methods. This approach simplifies data management and ensures better compatibility with other WooCommerce plugins and extensions.

3. **Utilize Order Notes for Tracking**
   - To track the flow of notifications and payment stages, consider adding WooCommerce order notes instead of custom metadata for logs. Order notes are visible in the order’s history and can be helpful for both customers and administrators.
   
   ```php
   $order->add_order_note(__('Payment successful - IPG notification received', 'novabanka-ipg-gateway'));
   ```

4. **Standardize Data Storage**
   - When storing custom transaction data that doesn't fit into existing WooCommerce fields, ensure that field names are standardized and clear. Consider grouping fields logically (e.g., all IPG-related fields start with `_novabankaipg_`) to avoid conflicts and maintain data consistency.

   ```php
   $order->update_meta_data('_novabankaipg_payment_status', $notification['result']);
   $order->update_meta_data('_novabankaipg_auth_code', $notification['auth']);
   $order->save();
   ```

5. **Minimize Custom Data Storage**
   - Wherever possible, avoid adding too many custom fields to the order. Instead, leverage WooCommerce’s core methods and structures for tracking payment states and results. This practice keeps the database optimized and prevents performance degradation, especially for stores with large volumes of transactions.

6. **Data Privacy and Compliance**
   - Avoid storing sensitive information such as full card details in order metadata. Only store the last four digits if needed for customer reference. Ensure compliance with PCI-DSS standards by storing minimal cardholder information.
   
   ```php
   // Store only the last four digits for reference
   $order->update_meta_data('_novabankaipg_card_last_four', $notification['cardLastFourDigits']);
   $order->save();
   ```

These strategies will help ensure that the implementation remains maintainable, scalable, and compatible with future WooCommerce updates, while also optimizing performance and data security.
