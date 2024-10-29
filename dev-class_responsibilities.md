## Analysis of Code Overlap and Adherence to DRY and SoC Principles

To analyze the code overlap and adherence to DRY (Don't Repeat Yourself) and SoC (Separation of Concerns) principles across the specified classes, let's break down the responsibilities and identify any redundancies or violations:

1. **NovaBankaIPGGateway**
   - **Responsibilities**: Integrates with WooCommerce as a payment gateway.
   Processes payments through `PaymentService`.
     - Handles notifications through `NotificationService`.
     - Manages WooCommerce-specific settings and actions.
   - **Issues**: 
     - May contain business logic that should be in `PaymentService` or `NotificationService`.
     - Should primarily delegate tasks to services.

2. **NovaBankaIPGException**
   - **Responsibilities**: Custom exception handling for the plugin.
   - **Issues**: 
     - Should only define exceptions, no overlap expected.

3. **NotificationService**
   - **Responsibilities**: Processes IPG notifications, updates order statuses.
   Validates notification data.
     - Updates order statuses based on the notification.
     - Logs notification processing results.
     - Manages error handling specific to notification processing.
   - **Issues**: 
     - Ensure it doesn't duplicate logging or validation logic found in `Logger` or `SharedUtilities`.

4. **PaymentService**
   - **Responsibilities**: Handles payment processing, interacts with IPG.
   - **Issues**: 
     - Ensure no duplicate logic for amount formatting or API calls, which should be in `SharedUtilities` or `APIHandler`.

5. **APIHandler**
   - **Responsibilities**: Handles HTTP communication with the NovaBanka IPG API.
    - Manages request/response formatting.
     - Handles API errors.
   - **Issues**: 
     - Ensure it doesn't handle business logic, which should be in services.

6. **Config**
   - **Responsibilities**: Manages configuration settings.
   - **Issues**: 
     - Ensure it doesn't duplicate logic for determining test/live mode, which should be centralized.

7. **Logger**
   - **Responsibilities**: Handles logging operations.
   - **Issues**: 
     - Ensure it doesn't duplicate sensitive data handling, which should be in `SharedUtilities`.

8. **MessageHandler**
   - **Responsibilities**: Manages message verification and signature generation for IPG communication.
   - Verifies notification signatures.
   - Generates message verifiers.
   - **Issues**: 
     - Ensure it doesn't duplicate logic for data formatting or validation.

9. **SharedUtilities**
   - **Responsibilities**: Provides common utility functions used across various components.
     - Data formatting and validation.
     - Redacting sensitive data.
     - Generating API endpoints.
   - **Issues**: 
     - Ensure it centralizes common logic like data formatting, validation, and logging.

10. **ThreeDSHandler**
    - **Responsibilities**: Manages 3D Secure authentication.
    - **Issues**: 
      - Ensure it doesn't duplicate API call logic, which should be in `APIHandler`.

### Recommendations for Refactoring:

1. **Centralize Common Logic**:
   - Move common data formatting and validation to `SharedUtilities`.
   - Ensure all logging is handled by `Logger`.

2. **Delegate Responsibilities**:
   - `NovaBankaIPGGateway` should delegate all business logic to `PaymentService` and `NotificationService`.
   - `APIHandler` should only handle HTTP requests, not business logic.

3. **Avoid Redundancies**:
   - Ensure `PaymentService` and `NotificationService` do not duplicate logic for handling API responses or logging.
   - Use `Config` for all configuration-related logic, especially for test/live mode checks.

4. **Enhance SoC**:
   - Each class should have a single responsibility. For example, `ThreeDSHandler` should only manage 3D Secure processes.

5. **DRY Principle**:
   - Avoid repeating code for data validation, logging, and API interactions. Use utility classes where possible.
