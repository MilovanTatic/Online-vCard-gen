# Redirect Payment Implementation Guide

## Overview
The NovaBanka IPG Gateway implements a redirect-based payment flow where customers are directed to the bank's hosted payment page (HPP) for secure card data entry. This approach eliminates the need for direct card data handling within WordPress.

## Implementation Details

### 1. Gateway Configuration
The gateway extends WC_Payment_Gateway and implements a simplified payment form:

php
class NovaBankaIPGGateway extends WC_Payment_Gateway {
public function payment_fields() {
if ($this->description) {
echo wpautop(wptexturize($this->description));
}
echo '<button type="submit" class="button alt" id="novabankaipg-pay-button">' .
esc_html('Proceed to Payment', 'novabanka-ipg-gateway') .
'</button>';
}
}


## 2. Payment Flow
1. Customer clicks "Proceed to Payment" button
2. System initializes payment (see payment_data structure below)
3. Customer is redirected to bank's HPP
4. Bank handles 3DS authentication
5. Customer is returned to site with payment result

### 3. Payment Data Structure
Reference to implementation:

php:includes/Core/class-novabankaipggateway.php
startLine: 281
endLine: 292


### 4. Response Handling
The gateway asynchronously handles payment notifications:

php:includes/Core/class-novabankaipggateway.php
startLine: 398
endLine: 403

## Configuration Requirements

### 1. Gateway Settings
Required configuration in WooCommerce:
- Terminal ID
- Terminal Password
- Secret Key
- Response/Error URLs

### 2. SSL Requirements
- Valid SSL certificate
- Proper webhook configuration
- Secure response handling

## Testing Scenarios

### 1. Successful Payment

php
// Test successful payment flow
$test_data = [
'order_id' => 123,
'amount' => 100.00,
'currency' => 'USD'
];

### 2. Failed Payment
Reference test cards from documentation:
php:developer-documentation.md
startLine: 140
endLine: 145

### 3. Cancelled Payment
Test user cancellation on HPP as documented:
php:developer-documentation.md
startLine: 148
endLine: 153

## Error Handling
Reference error handling implementation:
php:developer-documentation.md
startLine: 254
endLine: 269

## Customization
The redirect flow can be customized using filters:
php:developer-documentation.md
startLine: 239
endLine: 250

## Security Considerations
1. Always verify payment notifications using message verification
2. Store payment IDs securely
3. Implement proper nonce checks
4. Log all payment events for audit

For message verification implementation, see:
php:developer-documentation.md
startLine: 168
endLine: 179

## Order Status Management
Reference order status handling:
php:developer-documentation.md
startLine: 183
endLine: 191

```