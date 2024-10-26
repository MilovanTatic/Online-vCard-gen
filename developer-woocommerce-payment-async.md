# WooCommerce Asynchronous Payment Flow - NovaBanka IPG

## Overview

The payment flow involves server-to-server communication between IPG and WooCommerce through a notification callback system.

## Flow Diagram

Customer         Our Site              IPG                Bank
   |                |                   |                   |
   |---(1)Checkout->|                   |                   |
   |                |---(2)PaymentInit->|                   |
   |                |<-(3)HPP URL-------|                   |
   |<-(4)Redirect---|                   |                   |
   |                |                   |                   |
   |---(5)Enter Card Details---------->|                   |
   |                |                   |---(6)3DS Auth---->|
   |                |                   |<-(7)Auth Response-|
   |                |<-(8)POST Notification-|               |
   |                |---(9)Return URL-->|                   |
   |<-(10)Final Redirect---------------|                   |

## Communication Steps

1. **Initial Order Creation**
   - Customer places order on WooCommerce site
   - Order status: 'pending'

2. **Payment Initialization**
   ```php
   // Send to IPG
   $payment_data = [
       'responseURL' => WC()->api_request_url('novabankaipg'),  // Notification callback URL
       'errorURL'    => $order->get_checkout_payment_url(true)  // Error return URL
   ];
   ```

3. **HPP Redirection**
   - IPG returns HPP URL
   - Customer redirected to IPG domain
   - Order status: 'on-hold'

4. **Payment Processing**
   - Customer completes payment on IPG
   - IPG processes with bank
   - Order status unchanged

5. **Payment Notification**
   ```php
   // IPG POSTs to our notification URL
   add_action('woocommerce_api_novabankaipg', array($this, 'handle_notification_callback'));
   ```

6. **Order Update & Response**
   - Process notification
   - Update order status
   - Return browserRedirectionURL to IPG

## API Endpoints

### 1. Notification Callback Endpoint
```php
// /wc-api/novabankaipg
public function handle_notification_callback() {
    try {
        // Get POST data from IPG
        $notification = $this->get_post_data();
        
        // Verify notification signature
        if (!$this->api_handler->verify_signature($notification)) {
            throw new Exception('Invalid signature');
        }
        
        // Process payment result
        $order = $this->process_payment_result($notification);
        
        // Prepare response for IPG
        $response = [
            'msgName' => 'PaymentNotificationResponse',
            'version' => '1',
            'paymentID' => $notification['paymentid'],
            'browserRedirectionURL' => $this->get_return_url($order)
        ];
        
        // Add message verifier
        $response['msgVerifier'] = $this->api_handler->generate_message_verifier([
            $response['msgName'],
            $response['version'],
            $response['paymentID'],
            $this->get_secret_key(),
            $response['browserRedirectionURL']
        ]);
        
        // Return JSON response to IPG
        wp_send_json($response);
        
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage(), 500);
    }
}
```

## Order Status Flow
```
pending -> on-hold -> processing/failed -> completed
```

- **pending**: Initial order creation
- **on-hold**: During HPP/3DS process
- **processing**: Successful payment
- **failed**: Failed payment
- **completed**: Order fulfilled

## Security Considerations

1. **Notification Verification**
   ```php
   // Verify IPG signature
   if (!$this->api_handler->verify_signature($notification)) {
       throw new Exception('Invalid signature');
   }
   ```

2. **Order State Validation**
   - Check current order status
   - Prevent duplicate processing
   - Validate payment amounts

3. **Response Signing**
   ```php
   // Generate response signature
   $msgVerifier = $this->api_handler->generate_message_verifier([
       'PaymentNotificationResponse',
       '1',
       $payment_id,
       $secret_key,
       $redirect_url
   ]);
   ```

## Implementation Notes

1. **Notification Handling**
   ```php
   private function process_payment_result($notification) {
       $order = wc_get_order($notification['trackid']);
       
       if (!$order) {
           throw new Exception('Order not found');
       }
       
       // Store transaction data
       $this->store_transaction_data($order, $notification);
       
       // Update order status based on result
       if ($notification['result'] === 'CAPTURED') {
           $order->payment_complete($notification['tranid']);
       } else {
           $order->update_status('failed', 'Payment failed: ' . $notification['result']);
       }
       
       return $order;
   }
   ```

2. **Data Storage**
   ```php
   private function store_transaction_data($order, $notification) {
       $order->update_meta_data('_novabankaipg_payment_id', $notification['paymentid']);
       $order->update_meta_data('_novabankaipg_transaction_id', $notification['tranid']);
       $order->update_meta_data('_novabankaipg_result', $notification['result']);
       $order->update_meta_data('_novabankaipg_auth_code', $notification['auth']);
       $order->save();
   }
   ```

## Testing Considerations

1. **Notification Testing**
   - Test with IPG test environment
   - Verify signature validation
   - Test all payment result scenarios

2. **Error Handling**
   - Test invalid signatures
   - Test missing order IDs
   - Test duplicate notifications

3. **Response Validation**
   - Verify response format
   - Test signature generation
   - Validate redirect URLs

## Debugging

1. **Enable Logging**
   ```php
   $this->logger->debug('Payment notification received', [
       'notification' => $notification,
       'order_id' => $order_id
   ]);
   ```

2. **Monitor Endpoints**
   - Check WooCommerce logs
   - Monitor notification callbacks
   - Track customer returns

3. **Verify Data**
   - Check order meta data
   - Verify transaction IDs
   - Validate amounts and currencies

## Role and Flow Mapping

### Component Roles
- WooCommerce (Our Plugin) = Merchant Implementation
- IPG = Payment Gateway Service
- Plugin Components = Integration Layer

### Flow Comparison
Your Flow:                     | Documentation Flow:
-----------------------------|-----------------------------
Woo Checkout -> Pay Now      | Merchant Order Form
Plugin Handles Init          | Merchant Backend
IPG HPP                      | IPG HPP
Bank Auth                    | Bank Auth
IPG Response                | IPG Response
Plugin Handles Response     | Merchant Backend
Woo Order Status Update     | Merchant Order Update

### Integration Points


## Data Storage Strategy

### Transaction Data Storage
```php
private function store_transaction_data($order, $notification) {
    // Store all transaction data
    foreach ($notification as $key => $value) {
        $order->update_meta_data("_novabankaipg_{$key}", $value);
    }
    
    // Store raw response for debugging
    $order->update_meta_data('_novabankaipg_raw_response', wp_json_encode($notification));
    
    $order->save();
}
```

### Required Data Fields
1. From IPG to Store:
   - PaymentID
   - TransactionID
   - Auth Code
   - Card Type
   - Card Last 4 Digits
   - Response Codes
   - All IPG Messages

2. Send to IPG:
   - Order ID (trackid)
   - Virtual Product SKU (udf1)
   - Customer Email (buyerEmailAddress)
   - Customer Phone (udf2)
   - Payment Amount (amt)
   - Currency (currencycode)

## Message Verification
```php
private function generate_message_verifier($fields) {
    $message = implode('', array_filter($fields));
    $message = preg_replace('/\s+/', '', $message);
    return base64_encode(hash('sha256', $message, true));
}
```
