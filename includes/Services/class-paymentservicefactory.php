<?php
/**
 * PaymentServiceFactory Class
 *
 * Factory class for creating PaymentService instances with proper dependencies.
 * Follows factory pattern to ensure consistent PaymentService initialization.
 *
 * @package NovaBankaIPG\Services
 * @since 1.0.1
 */

namespace NovaBankaIPG\Services;

use NovaBankaIPG\Utils\Logger;
use NovaBankaIPG\Utils\APIHandler;
use NovaBankaIPG\Utils\DataHandler;

/**
 * Factory for creating PaymentService instances.
 */
class PaymentServiceFactory {
	/**
	 * Create a new PaymentService instance.
	 *
	 * @param Logger      $logger       Logger instance.
	 * @param APIHandler  $api_handler  API handler instance.
	 * @param DataHandler $data_handler Data handler instance.
	 * @return PaymentService
	 */
	public static function create(
		Logger $logger,
		APIHandler $api_handler,
		DataHandler $data_handler
	): PaymentService {
		return new PaymentService( $logger, $api_handler, $data_handler );
	}
}
