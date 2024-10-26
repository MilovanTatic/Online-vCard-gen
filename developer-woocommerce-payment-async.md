# WooCommerce Asynchronous Payment Flow - NovaBanka IPG

## Overview

The payment flow involves multiple steps across different domains with asynchronous communication between IPG and WooCommerce.

## Flow Diagram

Customer Our Site IPG Bank
| | | |
|---(1)Place Order--------->| | |
| |---(2)PaymentInit------->| |
| |<---(3)HPP URL-----------| |
|<---(4)Redirect to HPP-----| | |
| | | |
|---(5)Enter Card Details------------------------->| |
| | |---(6)3DS Auth------------>|
| | |<---(7)Auth Response-------|
| |<---(8)Payment Notification| |
| |---(9)Process Order----->| |
|<---(10)Redirect to Result-| | |


## Communication Steps

1. **Initial Order Creation**
   - Customer places order on WooCommerce site
   - Order status: 'pending'

2. **Payment Initialization**
   ```php
   // Send to IPG
   $payment_data = [
       'response_url' => WC()->api_request_url('novabankaipg'),
       'success_url' => $success_url,
       'failure_url' => $failure_url
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

5. **Asynchronous Notification**
   ```php
   // IPG calls our notification URL
   add_action('woocommerce_api_novabankaipg', array($this, 'handle_gateway_response'));
   ```

6. **Order Update**
   - Process notification
   - Update order status
   - Store transaction data

7. **Customer Return**
   ```php
   // Handle customer return separately
   add_action('woocommerce_api_novabankaipg_success', array($this, 'handle_success_return'));
   add_action('woocommerce_api_novabankaipg_failure', array($this, 'handle_failure_return'));
   ```

## API Endpoints

### 1. Notification Endpoint

php
// /wc-api/novabankaipg
public function handle_gateway_response() {
// Process IPG notification
// Update order status
// Return response to IPG
}


### 2. Success Return Endpoint

php
// /wc-api/novabankaipg_success
public function handle_success_return() {
// Verify order
// Redirect to thank you page
}

### 3. Failure Return Endpoint

php
// /wc-api/novabankaipg_failure
public function handle_failure_return() {
// Verify order
// Show error message
// Redirect to payment page
}

## Order Status Flow


plaintext
pending -> on-hold -> processing/failed -> completed


- **pending**: Initial order creation
- **on-hold**: During HPP/3DS process
- **processing**: Successful payment
- **failed**: Failed payment
- **completed**: Order fulfilled

## Security Considerations

1. **URL Validation**
   ```php
   // Verify order key
   $order_key = wc_clean($_GET['order-key'] ?? '');
   $order = wc_get_orders(['order_key' => $order_key, 'limit' => 1])[0] ?? null;
   ```

2. **Notification Verification** to be implemented later after debugging
   ```php
   // Verify IPG signature
   if (!$this->api_handler->verify_signature($notification)) {
       throw new Exception('Invalid signature');
   }
   ```

3. **Order State Validation**
   - Check current order status
   - Prevent duplicate processing
   - Validate payment amounts

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
   - Test with IPG test environment
   - Verify all notification scenarios
   - Check order status transitions

2. **Customer Return Testing**
   - Test success/failure redirects
   - Verify order status display
   - Check error message handling

3. **Security Testing**
   - Test invalid signatures
   - Test invalid order keys
   - Test duplicate notifications

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

