```markdown
# NovaBanka IPG Gateway Documentation

## Overview
The NovaBanka IPG Gateway plugin provides 3D Secure payment integration for WooCommerce. This documentation covers installation, configuration, and implementation details for developers.

## Table of Contents
1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Integration Flow](#integration-flow)
4. [Testing](#testing)
5. [Common Issues](#common-issues)
6. [API Reference](#api-reference)

## Installation

### Requirements
- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- SSL Certificate (required for production)

### Setup Steps
1. Upload plugin to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Configure through WooCommerce → Settings → Payments

```php
// Example plugin activation check
add_action('plugins_loaded', function() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>NovaBanka IPG requires WooCommerce to be installed and active.</p></div>';
        });
        return;
    }
    // Initialize gateway
});
```

## Configuration

### Gateway Settings
```php
// Example configuration
$settings = [
    'enabled' => 'yes',
    'title' => 'Credit Card (3D Secure)',
    'description' => 'Pay securely using your credit card',
    'testmode' => 'yes',  // For testing
    'terminal_id' => 'YOUR_TERMINAL_ID',
    'terminal_password' => 'YOUR_TERMINAL_PASSWORD',
    'secret_key' => 'YOUR_SECRET_KEY'
];
```

### Environment URLs
- Test: `https://test-gateway.example.com/api`
- Production: `https://gateway.example.com/api`

## Integration Flow

### 1. Payment Initialization
```php
// Example payment initialization
try {
    $payment_data = [
        'amount' => $order->get_total(),
        'currency' => $order->get_currency(),
        'trackid' => $order->get_id(),
        'responseURL' => $notification_url,
        'errorURL' => $error_url
    ];
    
    $response = $api_handler->sendPaymentInit($payment_data);
    
    // Store payment ID
    update_post_meta($order_id, '_novabankaipg_payment_id', $response['paymentid']);
    
    // Redirect to HPP
    return [
        'result' => 'success',
        'redirect' => $response['browserRedirectionURL']
    ];
} catch (NovaBankaIPGException $e) {
    // Handle error
}
```

### 2. Notification Handling
```php
// Example notification handler
public function handle_notification() {
    try {
        $notification = json_decode(file_get_contents('php://input'), true);
        
        if ($this->api_handler->verifyNotification($notification)) {
            $order = wc_get_order($notification['trackid']);
            
            if ($notification['result'] === 'CAPTURED') {
                $order->payment_complete($notification['tranid']);
            } else {
                $order->update_status('failed');
            }
            
            echo json_encode([
                'msgName' => 'PaymentNotificationResponse',
                'paymentID' => $notification['paymentid'],
                'browserRedirectionURL' => $this->get_return_url($order)
            ]);
            exit;
        }
    } catch (Exception $e) {
        // Handle error
    }
}
```

## Testing

### Test Cards
```
Success Card: 4012001037141112
Failure Card: 4539990000000020
```

### Test Cases
1. Successful Payment
```php
// Test successful payment
$test_data = [
    'card_number' => '4012001037141112',
    'expiry' => '12/25',
    'cvv' => '123'
];
```

2. Failed Payment
```php
// Test failed payment
$test_data = [
    'card_number' => '4539990000000020',
    'expiry' => '12/25',
    'cvv' => '123'
];
```

3. Cancelled Payment
```php
// Test cancel scenario
// User clicks cancel on HPP
// Check notification handling
```

### Response Codes
```php
const RESPONSE_CODES = [
    '00' => 'Approved',
    '51' => 'Insufficient funds',
    '05' => 'Do not honor'
];
```

## Common Issues

### Message Verifier Mismatch
```php
// Correct message verifier generation
$message = implode('', [
    $request['msgName'],
    $request['version'],
    $request['id'],
    $request['password'],
    $request['amt'],
    $request['trackid']
]);
$message = preg_replace('/\s+/', '', $message);
return base64_encode(hash('sha256', $message, true));
```

### Order Status Updates
```php
// Proper order status handling
public function update_order_status($order, $notification) {
    if ($notification['result'] === 'CAPTURED') {
        $order->payment_complete($notification['tranid']);
        $order->add_order_note('Payment completed via 3DS');
    } else {
        $order->update_status('failed', 'Payment failed: ' . $notification['result']);
    }
}
```

## API Reference

### APIHandler Interface
```php
interface APIHandler {
    public function sendPaymentInit(array $data): array;
    public function verifyNotification(array $notification): bool;
    public function generateNotificationResponse(string $payment_id, string $redirect_url): array;
}
```

### DataHandler Interface
```php
interface DataHandler {
    public function formatAmount($amount): string;
    public function getCurrencyCode(string $currency): string;
    public function validateRequiredFields(array $data, array $required): bool;
}
```

### Logger Interface
```php
interface Logger {
    public function debug(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
}
```

### Hooks Reference

#### Actions
```php
// Before payment initialization
do_action('novabankaipg_before_payment_init', $order);

// After successful payment
do_action('novabankaipg_payment_complete', $order, $notification);

// After failed payment
do_action('novabankaipg_payment_failed', $order, $notification);
```

#### Filters
```php
// Modify payment data before sending
add_filter('novabankaipg_payment_data', function($data, $order) {
    // Modify data
    return $data;
}, 10, 2);

// Customize return URL
add_filter('novabankaipg_return_url', function($url, $order) {
    // Modify URL
    return $url;
}, 10, 2);
```

### Error Handling
```php
try {
    // Payment processing
} catch (NovaBankaIPGException $e) {
    // Handle specific gateway errors
    $error_type = $e->getErrorType();
    $error_data = $e->getErrorData();
    
    // Log error
    $logger->error($e->getMessage(), [
        'error_type' => $error_type,
        'error_data' => $error_data
    ]);
    
    // Display user-friendly message
    wc_add_notice('Payment error: ' . $e->getMessage(), 'error');
}
```

### Customization Examples

#### Custom Payment Fields
```php
add_filter('novabankaipg_payment_fields', function($fields) {
    $fields['custom_field'] = [
        'label' => 'Custom Field',
        'required' => true
    ];
    return $fields;
});
```

#### Custom Order Processing
```php
add_action('novabankaipg_before_process_payment', function($order) {
    // Add custom processing
    $order->update_meta_data('_custom_data', 'value');
});
```

For additional support or queries, please refer to the [support documentation](https://claude.site/artifacts/1ad4aa8f-4462-48b0-a5e8-68cb0fc3d126)

```
