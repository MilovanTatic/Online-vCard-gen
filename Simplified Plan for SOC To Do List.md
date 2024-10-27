### To-Do List for Completing SoC Refactor Based on Code Comparison

After comparing the provided old and refactored codebases, here are the steps required to ensure all components from the old implementation are fully transitioned into the new structure and aligned with the documented Separation of Concerns (SoC).

Complete OLD codebase: /home/financhssh/webprojects/financ/wp-content/plugins/gateway-33/repopack-output-main-branch.xml
Complete NEW codebase: /home/financhssh/webprojects/financ/wp-content/plugins/gateway-33/repopack-output.xml


#### 1. **Reintroduce Data Handling Utility (`DataHandler`)**
- **Issue**: The `DataHandler` class from the old code is not present in the refactored codebase.
- **Action**: Add the `DataHandler` class to the `/Utils` folder. This class should be responsible for formatting payment amounts, phone numbers, item amounts, and validating language codes, as in the old implementation.
- **Details**:
  - Ensure methods like `format_amount()`, `format_phone()`, and `validate_language_code()` are included.
  - This class should be used within the `PaymentService` and potentially within other utilities for consistent data formatting.

#### 2. **Reintegrate 3D Secure Handling (`ThreeDSHandler`)**
- **Issue**: The `ThreeDSHandler` utility is missing in the new codebase, but it was previously managing the 3D Secure (3DS) authentication process.
- **Action**: Add `ThreeDSHandler` to `/Utils`.
  - Ensure it handles the interaction with the 3D Secure mechanism, as part of the payment flow.
  - Reconnect `ThreeDSHandler` to the `PaymentService` so that 3D Secure verifications are executed appropriately.

#### 3. **Revise the Notification Handling Logic**
- **Issue**: While the `NotificationService` has been created, some of the finer aspects of handling notifications, such as specific error responses or signature verification, might be missing.
- **Action**: Double-check the `NotificationService` against the original notification handling code to ensure all logic has been carried over, particularly signature verification and response management.
- **Details**:
  - Make sure the notification verification (`verify_signature`) logic is implemented consistently.
  - Properly manage different notification types, including success, failure, and error scenarios.

#### 4. **Add Test Mode and Debug Logging Settings**
- **Issue**: The previous version had detailed settings for managing test and debug modes, which appear to be partially simplified or omitted in the refactor.
- **Action**: Reinstate `test_mode` and `debug` settings within `Config`.
  - Ensure the `Logger` utility can be set to different levels of verbosity based on the `debug` flag.
  - Test mode should control which API endpoint is used (live vs. test) and other related settings, like dummy credentials.

#### 5. **Enhance Logger Utility (`Logger`)**
- **Issue**: Logging is centralized, but the previous code included different log levels (`debug`, `info`, `warning`, `error`, `critical`).
- **Action**: Expand the `Logger` class to fully support different log levels.
  - Reintroduce log level configuration to provide more granular control over what gets logged.
  - Ensure all classes (`PaymentService`, `NotificationService`, `APIHandler`) use the appropriate log levels.

#### 6. **Update JavaScript for Enhanced User Interaction**
- **Issue**: Front-end JavaScript (`ipg-admin.js`, `ipg-scripts.js`) needs to be properly separated by concern.
- **Action**:
  - Split form validation logic into a separate module (e.g., `validation.js`) from the initialization and event handling logic.
  - Ensure each JavaScript module has a specific focus, such as form validation, API communication, or UI event handling.

#### 7. **Integrate Missing API Methods in `APIHandler`**
- **Issue**: Some methods from the old `APIHandler` may not have been transferred, such as those dealing with refund processing or notification verification.
- **Action**: Add missing methods to the `APIHandler` class for a complete set of API interactions.
  - Ensure methods like `process_refund()`, `verify_notification()`, and `send_payment_init()` are properly included.
  - `APIHandler` should focus solely on handling the HTTP requests and responses with the external IPG.

#### 8. **Revise Refund Handling in `PaymentService`**
- **Issue**: The refund logic (`process_refund()`) in the refactored `PaymentService` appears simplified compared to the original.
- **Action**: Update `PaymentService` to include more comprehensive refund handling logic.
  - Reintroduce validation checks, logging, and API call management as seen in the old codebase.
  - Ensure refunds are processed using the `APIHandler` and are consistent with the original flow.

#### 9. **Add Consistent Exception Handling**
- **Issue**: Custom exception handling (`NovaBankaIPGException`) is present, but it is not consistently applied across all classes.
- **Action**: Ensure `NovaBankaIPGException` is used uniformly across the codebase.
  - Replace generic PHP exceptions with `NovaBankaIPGException` where applicable.
  - Make sure each exception includes meaningful messages and additional context for easier debugging.

#### 10. **Adjust WooCommerce Hooks and Settings Initialization**
- **Issue**: Some WooCommerce-specific hooks and settings (e.g., for receipt pages or order processing) might not have been transferred.
- **Action**:
  - Review WooCommerce hook initialization in `NovaBankaIPGGateway` to make sure all previously supported hooks are in place.
  - Reintroduce receipt page and order status hooks as necessary.

class-novabankaipg.php *main plugin file*
  /assets/
    /js
      ipg-admin.js
      ipg-scripts.js
    /css
    ipg-admin.css
      ipg-styles.css
  /includes
    /Core
      class-novabankaipggateway.php        // Only handles WooCommerce integration.
    /Services
      class-paymentservice.php             // Payment business logic.
      class-notificationservice.php        // Manages incoming callbacks and notifications.
  /Utils
    class-apihandler.php                 // HTTP communication.
    class-logger.php                     // Logging utility, includes log levels.
    class-config.php                     // Configuration handling.
    class-datahandler.php                // Data formatting and validation.
    class-threedshandler.php             // Handles 3DS processing.
  /Exceptions
    class-novabankaipgexception.php      // Custom exception handling.

Various other developer notes and ASoft notes are in the codebase. This SOC To Do List is based on the code comparison and the notes.

### Summary
This to-do list is designed to fully integrate the key elements from the old codebase into the new refactored structure, ensuring alignment with the SoC principles. The steps focus on improving utility handling, ensuring comprehensive error management, reinstating key functional parts (e.g., data and 3D Secure handlers), and enhancing consistency across the codebase, all while following WooCommerce and WordPress best practices.
