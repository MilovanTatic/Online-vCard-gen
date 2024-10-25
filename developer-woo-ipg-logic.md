# NovaBanka IPG Integration Logic

This document outlines the core logic and workflow for integrating the NovaBanka Internet Payment Gateway (IPG) with WooCommerce considering the Asoft IPG 3DS eCommerce Transaction flow and the AsoftIPG_Merchant_Integration.Guide_Payment3DS_v12.txt documentation.

Woocommerce=Merchant
IPG=IPG
Woocommerce Payment plugin / extends WP and WOO functionality

### The Buyer Perspective ​

1. Chooses products.  
2. Enters personal details for shipment and clicks "Buy". ​  
3. Is redirected to the HPP.  
4. Enters credit card data and clicks "Pay".  
5. If the card is 3-D Secure enabled, the Buyer is redirected to their bank's website to enter the password and then returns to the HPP. ​  
6. Is redirected to a specific page on the Merchant website displaying the payment result. ​  
7. Receives an email notification of payment if enabled by the Merchant. ​

### The Merchant Perspective ​

1. Receives a purchase order from the Buyer. ​  
2. Sends a PaymentInit message to IPG. ​  
3. Receives a unique PaymentID and the URL of the HPP. ​  
4. Redirects the Buyer to the HPP URL with the PaymentID. ​  
5. Receives a transaction notification from IPG. ​  
6. Responds with the URL for the Buyer to be redirected to for the transaction result. ​  
7. Presents the result to the Buyer. ​  
8. Receives an email notification of payment if enabled. 

### The IPG Perspective ​

1. Receives a PaymentInit message from the Merchant. ​  
2. Responds with the HPP URL and a PaymentID. ​  
3. Presents the HPP to the Buyer. ​  
4. Receives the Buyer's credit card data. ​  
5. If the card is 3-D Secure enabled, redirects the Buyer to the bank's site for authentication and awaits the result. ​  
6. Processes the transaction by sending the request to the credit card company and gets a response. ​  
7. Sends a result notification message to the Merchant. ​  
8. Receives the URL for Buyer redirection. ​  
9. Redirects the Buyer to the specified URL.


Woo Checkout -> Pay Now -> IPG <-> Woo <-> IPG -> Woo Succesful payment / Unsuccesful payment ?

Communication flow:

1. Buyer chooses Woocommerce Virtual Product with SKU 
2. Merchant prepares and returns the checkout page. 
3. Buyer fills out required fields (email and Phone No) and clicks "Pay NOW".  
4. Merchant sends PaymentInit request to IPG. ​  
5. IPG verifies the request, saves transaction data, and returns the HPP URL and PaymentID. ​  
6. Merchant saves the PaymentID and redirects the browser to the HPP URL with the PaymentID. ​  
7. IPG checks the PaymentID, prepares the payment page, and returns it to the Buyer's browser. ​  
8. Buyer enters necessary data and clicks "Pay".  
9. If 3-D Secure, IPG redirects the browser to the bank's site for authentication. ​  
10. Buyer provides authentication data and is redirected back to IPG.  
11. IPG combines data and sends the request to the authorization system. ​  
12. Authorization system processes the request and returns the result to IPG. ​  
13. IPG sends a POST message to the Merchant with the transaction result. ​  
14. Merchant updates the transaction status and returns the URL for Buyer redirection. ​  
15. IPG redirects the browser to the specified URL and displays the final page with payment details. ​  
16. Buyer gets the Merchant result page - Succesful payment / Unsuccesful payment
17. Merchant stores the order data, with IPG responses in Woocommerce and change status.

Wocoomerce Order Management statuses (should be named by woo standards, utilizing standard woo functionality):
1. Init Order
2. Partial Order (not succesful due cancelation of HPP flow for any reason / stored init data + reason)
3. Complete Order (Succesful transaction + stored IPG messages)
4. Uncomplete Order (Unsuccesful transaction + stored IPG messages)


## Role Mapping

WooCommerce (Our Plugin) = Merchant Implementation
IPG = Payment Gateway Service
Plugin Components = Integration Layer

## Flow Comparison

Your Flow:                     | Documentation Flow:
-----------------------------|-----------------------------
Woo Checkout -> Pay Now      | Merchant Order Form
Plugin Handles Init          | Merchant Backend
IPG HPP                      | IPG HPP
Bank Auth                   | Bank Auth
IPG Response                | IPG Response
Plugin Handles Response     | Merchant Backend
Woo Order Status Update     | Merchant Order Update

Current Implementation:
WooCommerce Checkout -> Gateway Class -> APIHandler -> IPG

Documentation Flow:
Merchant Site -> PaymentInit -> HPP -> 3DS -> Bank -> Response

## Key Differences

We are integrating with WooCommerce's existing order system
Documentation assumes standalone merchant implementation
Our flow adds WooCommerce-specific states and hooks

## Key Integration Points

a) Order Creation (WooCommerce)
b) Payment Initialization (Our Gateway)
c) HPP Redirection (IPG)
d) 3DS Processing (Bank/IPG)
e) Response Handling (Our Gateway)
f) Order Status Updates (WooCommerce)

## Order Flow Integration

// WooCommerce States Mapping
'pending'    => 'Init Order',
'on-hold'    => 'Partial Order',
'completed'  => 'Complete Order',
'failed'     => 'Uncomplete Order'

## Communication Points

WooCommerce Checkout
↓
Plugin (PaymentInit)
↓
IPG (HPP + 3DS)
↓
Bank
↓
IPG (Process)
↓
Plugin (Notification)
↓
WooCommerce Order Update

## Data Storage Strategy

// Store in WooCommerce order meta
$order->update_meta_data('_novabankaipg_payment_id', $response['paymentid']);
$order->update_meta_data('_novabankaipg_transaction_data', $response);
$order->update_meta_data('_novabankaipg_status', $status);

## Complete Integrated Flow

1. Initial flow (WooCommerce)

// In Gateway Class
public function process_payment($order_id) {
    $order = wc_get_order($order_id);
    
    // Step 1: Create/Validate Order
    // Step 2: Prepare Payment Data
    // Step 3: Send PaymentInit
    // Step 4: Handle Redirect
}

2. Communication flow

Buyer -> WooCommerce Checkout
↓
Our Plugin (PaymentInit Request)
↓
IPG (HPP + 3DS if needed)
↓
Bank Authentication (if 3DS)
↓
IPG Processing
↓
Our Plugin (Notification)
↓
WooCommerce Order Update

3. Order States Mapping

// WooCommerce Order States
'pending'    => 'Init Order',         // After PaymentInit
'on-hold'    => 'Partial Order',      // During 3DS/HPP
'processing' => 'Processing Payment',  // After successful capture
'completed'  => 'Complete Order',      // After successful processing
'failed'     => 'Uncomplete Order'    // If payment fails

4. Data Storage Strategy

// Order Meta Storage
$order->update_meta_data('_novabankaipg_payment_id', $paymentid);
$order->update_meta_data('_novabankaipg_transaction_id', $tranid);
$order->update_meta_data('_novabankaipg_auth_code', $auth);
$order->update_meta_data('_novabankaipg_status', $status);

5. Message Verification

// In MessageHandler
private function generate_message_verifier($fields) {
    $message = implode('', array_filter($fields));
    $message = preg_replace('/\s+/', '', $message);
    return base64_encode(hash('sha256', $message, true));
}

## Detailed Flow Requirements

1. Transaction Type
- One-time payment (not recurring)
- Need to set RecurAction="" for normal e-commerce

2. Data Flow Requirements:

Send to IPG:
- Order ID (trackid)
- Virtual Product SKU (udf1)
- Customer Email (buyerEmailAddress)
- Customer Phone (udf2)
- Payment Amount (amt)
- Currency (currencycode)

Store from IPG:
- PaymentID
- TransactionID
- Auth Code
- Card Type
- Card Last 4 Digits
- Response Codes
- All IPG Messages

3. Flow States

WooCommerce Order:
pending -> on-hold (during HPP/3DS) -> processing/failed -> completed

## Updated Implementation Logic

1. Payment Data Structure
// In Gateway Class
$payment_data = [
    'action'       => '1',            // Purchase
    'amount'       => $order->get_total(),
    'currency'     => $order->get_currency(),
    'trackid'      => $order->get_id(),
    'response_url' => $this->get_return_url($order),
    'error_url'    => $order->get_checkout_payment_url(),
    'langid'       => $this->get_language_code(),
    // Customer Data
    'buyerEmailAddress' => $order->get_billing_email(),
    // UDF Fields
    'udf1'         => $this->get_product_sku($order),    // Product SKU
    'udf2'         => $order->get_billing_phone(),       // Phone Number
    'udf3'         => wp_create_nonce('novabankaipg_payment_' . $order_id), // Security
    // One-time payment
    'RecurAction'  => '',             // Normal e-commerce
    'payinst'      => 'VPAS',         // 3DS
];

2. Order Meta Storage

// After successful payment
$order->update_meta_data('_novabankaipg_payment_id', $notification['paymentid']);
$order->update_meta_data('_novabankaipg_transaction_id', $notification['tranid']);
$order->update_meta_data('_novabankaipg_auth_code', $notification['auth']);
$order->update_meta_data('_novabankaipg_card_type', $notification['cardtype']);
$order->update_meta_data('_novabankaipg_card_last4', $notification['cardLastFourDigits']);
$order->update_meta_data('_novabankaipg_response_code', $notification['responsecode']);
$order->update_meta_data('_novabankaipg_raw_response', wp_json_encode($notification));

3. Status Management

// In notification handler
public function process_notification($notification) {
    $order = wc_get_order($notification['trackid']);
    
    if ('CAPTURED' === $notification['result']) {
        $order->update_status('processing', __('Payment successful via IPG.', 'novabanka-ipg-gateway'));
        $this->store_transaction_data($order, $notification);
    } else {
        $order->update_status('failed', sprintf(
            __('Payment failed. Result: %s, Code: %s', 'novabanka-ipg-gateway'),
            $notification['result'],
            $notification['responsecode'] ?? 'N/A'
        ));
    }
    
    return [
        'msgName' => 'PaymentNotificationResponse',
        'browserRedirectionURL' => $this->get_return_url($order)
    ];
}

4. Helper Methods

private function get_product_sku($order) {
    $items = $order->get_items();
    $item = reset($items);  // Get first item
    $product = $item->get_product();
    return $product ? $product->get_sku() : '';
}

private function store_transaction_data($order, $notification) {
    // Store all transaction data
    foreach ($notification as $key => $value) {
        $order->update_meta_data("_novabankaipg_{$key}", $value);
    }
    
    // Store raw response for debugging
    $order->update_meta_data('_novabankaipg_raw_response', wp_json_encode($notification));
    
    $order->save();
}

## Conclusion

This implementation:
1. Properly handles one-time payments
2. Passes all required data to IPG
3. Stores complete transaction history
4. Maintains proper order status flow
5. Follows WooCommerce standards
6. Preserves all IPG response data
