### Simplified Plan for Separation of Concerns (SoC)

#### Refactor the Core Payment Gateway Class (`NovaBankaIPGGateway`)

**Responsibility**: The `NovaBankaIPGGateway` should only handle WooCommerce integration, gateway setup, and initial flow control.

**Actions**:
- Keep WooCommerce hooks (`add_gateway`, `handle_callback`, etc.) in this class.
- Move business logic related to payments and refunds to dedicated service classes.

#### Create a `PaymentService` Class

**Responsibility**: Handle the actual payment processing logic, including payment initialization and refunds.

**Actions**:
- Move methods related to processing payments and generating API requests, like `process_payment()` and `sendPaymentInit()`, to `PaymentService`.
- This service should interact with the IPG API and should not be directly aware of WooCommerce.

```php
class PaymentService {
    private $api_handler;
    private $logger;

    /**
     * Constructor for PaymentService.
     *
     * @param APIHandler $api_handler Handles HTTP communication.
     * @param Logger $logger Manages logging.
     */
    public function __construct($api_handler, $logger) {
        $this->api_handler = $api_handler;
        $this->logger = $logger;
    }

    /**
     * Initializes a payment for an order.
     *
     * @param WC_Order $order The WooCommerce order object to initialize payment for.
     * @return array Response from the API containing payment initiation details.
     */
    public function initializePayment($order) {
        // Extract payment logic here to handle interactions with the IPG API.
        return $this->api_handler->sendPaymentInit($order);
    }

    /**
     * Refunds a payment for an order.
     *
     * @param WC_Order $order The WooCommerce order object to refund.
     * @param float $amount The amount to be refunded.
     */
    public function refundPayment($order, $amount) {
        // Handle refund logic here, including communication with the IPG API.
    }
}
```

#### Simplify `APIHandler` for API Requests Only

**Responsibility**: Handle HTTP communication with the NovaBanka IPG API.

**Actions**:
- Ensure that `APIHandler` contains only methods to send API requests and receive responses.
- Avoid embedding business rules, such as checking WooCommerce order statuses, in `APIHandler`.

#### Centralize Configuration Handling with `Config` Utility

**Gateway Settings Management**:
- Use the WooCommerce settings API (`init_form_fields`) to keep all configuration in one place.
- Move configuration fetching logic from the main plugin class to a new `Config` utility class.
- The `Config` class should provide a central point for managing default values and retrieving settings safely.

```php
class Config {
    /**
     * Retrieves a setting value by its key.
     *
     * @param string $key The key of the setting to retrieve.
     * @return mixed The setting value, or null if it does not exist.
     */
    public static function getSetting($key) {
        $settings = get_option('woocommerce_novabankaipg_settings', []);
        return $settings[$key] ?? null;
    }
}
```

#### Extract Logging Logic to a `Logger` Utility

**Responsibility**: Handle all log-related actions.

**Actions**:
- Move all logging to a dedicated `Logger` class, using WordPress’s built-in `WC_Logger` where appropriate.
- This ensures that logging can be easily modified (e.g., switching to a different logging mechanism).

#### Centralized Error Handling Using Custom Exceptions

**Custom Exceptions**:
- Introduce `NovaBankaIPGException` for specific error scenarios, such as `InvalidOrderException` or `PaymentFailureException`.
- This makes error management clearer and allows different parts of the system to respond appropriately.

**Error Management**:
- Ensure that exception handling is centralized within the payment service and is properly logged.

#### Refactor Payment Initialization and Notification Handlers

**Payment Initialization (`process_payment()`)**:
- Remain in `NovaBankaIPGGateway` but delegate to `PaymentService` for the actual initialization.

**Notification Handler (`handle_notification_callback()`)**:
- Extract the notification callback to `NotificationService` to manage incoming callbacks from IPG.

#### Simplify Front-End JavaScript Handling

**Split Functionality**:
- Move form handling and validation logic to a dedicated JavaScript module (e.g., `validation.js`).
- Keep different concerns, such as event listeners and API requests, in separate files.

### Revised Folder Structure for SoC

```
/includes
    /Core
        NovaBankaIPGGateway.php        // Handles WooCommerce integration only
    /Services
        PaymentService.php             // Handles payment-related business logic
        NotificationService.php        // Manages IPG notifications
    /Utils
        APIHandler.php                 // Handles HTTP communication
        Logger.php                     // Centralized logging utility
        Config.php                     // Manages plugin configuration
/assets
    /js
        validation.js                  // Front-end form validation logic
        api-handler.js                 // AJAX handling for payments
```

### Benefits of This Simplified Refactor

- **Easier Maintenance**: Each class has a specific responsibility, making the codebase easier to read and maintain.
- **Reduced Coupling**: By extracting services (`PaymentService`, `NotificationService`), changes in payment logic do not affect the WooCommerce-specific integration logic.
- **Improved Testability**: Moving business logic into services allows unit tests to be implemented more effectively without requiring WooCommerce context.
- **Alignment with WooCommerce Standards**: Keeping WooCommerce-specific concerns inside the `NovaBankaIPGGateway` class ensures better alignment with WooCommerce coding guidelines.

### Step-by-Step Refactor Implementation

1. **Extract Payment Logic to `PaymentService`**:
   - Move methods from `NovaBankaIPGGateway` that deal with initiating and validating payments.

2. **Refactor `APIHandler`**:
   - Simplify to focus solely on handling HTTP requests and responses.

3. **Migrate Logging to `Logger` Utility**:
   - Replace in-line logging throughout the codebase with calls to `Logger` for consistency.

4. **Notification Management**:
   - Extract the notification handling logic to `NotificationService` to streamline incoming payment notifications.

5. **Frontend Improvements**:
   - Ensure JavaScript for handling payment buttons and forms is organized into distinct responsibilities, such as validation and API communication.

This refactor plan keeps changes manageable, aligns with WooCommerce standards, and significantly improves the separation of concerns across the plugin for better maintainability and extensibility.
