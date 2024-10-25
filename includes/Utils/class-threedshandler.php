<?php
/**
 * Class ThreeDSHandler
 *
 * Handles 3D Secure authentication flow and data processing.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.0
 */

namespace NovaBankaIPG\Utils;

use NovaBankaIPG\Exceptions\NovaBankaIPGException;

defined( 'ABSPATH' ) || exit;

/**
 * Class ThreeDSHandler
 *
 * Handles 3D Secure authentication flow and data processing.
 *
 * @package NovaBankaIPG\Utils
 * @since 1.0.0
 */
class ThreeDSHandler {
	/**
	 * API Handler instance
	 *
	 * @var ApiHandler
	 */
	private $api_handler;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Data Handler instance
	 *
	 * @var DataHandler
	 */
	private $data_handler;

	/**
	 * Constructor
	 *
	 * @param ApiHandler  $api_handler API handler instance.
	 * @param Logger      $logger Logger instance.
	 * @param DataHandler $data_handler Data handler instance.
	 */
	public function __construct( ApiHandler $api_handler, Logger $logger, DataHandler $data_handler ) {
		$this->api_handler  = $api_handler;
		$this->logger       = $logger;
		$this->data_handler = $data_handler;
	}

	/**
	 * Prepare 3DS data for PaymentInit request.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array
	 */
	public function prepare_3ds_data( array $order_data ): array {
		$threeds_data = array(
			'payinst'                                 => 'VPAS', // 3DS Secure payment instrument.
			'acctInfo'                                => $this->prepare_account_info( $order_data ),
			'threeDSRequestorAuthenticationInfo'      => $this->prepare_authentication_info( $order_data ),
			'threeDSRequestorPriorAuthenticationInfo' => $this->prepare_prior_auth_info( $order_data ),
		);

		$this->logger->debug( 'Prepared 3DS data', array( 'data' => $threeds_data ) );

		return $threeds_data;
	}

	/**
	 * Prepare account information for 3DS.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array
	 */
	private function prepare_account_info( array $order_data ): array {
		$user_id      = $order_data['user_id'] ?? 0;
		$account_data = array();

		if ( $user_id ) {
			$user                    = get_userdata( $user_id );
			$registration_date       = $user->user_registered;
			$days_since_registration = ( time() - strtotime( $registration_date ) ) / DAY_IN_SECONDS;

			// Account age indicator.
			if ( $days_since_registration < 1 ) {
				$account_data['chAccAgeInd'] = '02'; // Created during transaction.
			} elseif ( $days_since_registration < 30 ) {
				$account_data['chAccAgeInd'] = '03'; // Less than 30 days.
			} elseif ( $days_since_registration < 60 ) {
				$account_data['chAccAgeInd'] = '04'; // 30-60 days.
			} else {
				$account_data['chAccAgeInd'] = '05'; // More than 60 days.
			}

			$account_data['chAccDate'] = gmdate( 'Ymd', strtotime( $registration_date ) );

			// Account changes.
			$last_update = get_user_meta( $user_id, '_account_last_update', true );
			if ( $last_update ) {
				$days_since_update = ( time() - strtotime( $last_update ) ) / DAY_IN_SECONDS;

				if ( $days_since_update < 1 ) {
					$account_data['chAccChangeInd'] = '01'; // Changed during transaction.
				} elseif ( $days_since_update < 30 ) {
					$account_data['chAccChangeInd'] = '02'; // Less than 30 days.
				} elseif ( $days_since_update < 60 ) {
					$account_data['chAccChangeInd'] = '03'; // 30-60 days.
				} else {
					$account_data['chAccChangeInd'] = '04'; // More than 60 days.
				}

				$account_data['chAccChange'] = gmdate( 'Ymd', strtotime( $last_update ) );
			}

			// Password changes.
			$last_password_change = get_user_meta( $user_id, '_password_last_change', true );
			if ( $last_password_change ) {
				$days_since_pwd_change = ( time() - strtotime( $last_password_change ) ) / DAY_IN_SECONDS;

				if ( $days_since_pwd_change < 1 ) {
					$account_data['chAccPwChangeInd'] = '02'; // Changed during transaction.
				} elseif ( $days_since_pwd_change < 30 ) {
					$account_data['chAccPwChangeInd'] = '03'; // Less than 30 days.
				} elseif ( $days_since_pwd_change < 60 ) {
					$account_data['chAccPwChangeInd'] = '04'; // 30-60 days.
				} else {
					$account_data['chAccPwChangeInd'] = '05'; // More than 60 days.
				}

				$account_data['chAccPwChange'] = gmdate( 'Ymd', strtotime( $last_password_change ) );
			}

			// Transaction activity.
			$account_data['txnActivityDay']    = $this->get_transaction_count( $user_id, '24 hours' );
			$account_data['txnActivityYear']   = $this->get_transaction_count( $user_id, '1 year' );
			$account_data['nbPurchaseAccount'] = $this->get_transaction_count( $user_id, '6 months' );

			// Shipping address usage.
			if ( isset( $order_data['shipping_address'] ) ) {
				$address_first_use = $this->get_address_first_use( $user_id, $order_data['shipping_address'] );
				if ( $address_first_use ) {
					$days_since_first_use = ( time() - strtotime( $address_first_use ) ) / DAY_IN_SECONDS;

					if ( $days_since_first_use < 1 ) {
						$account_data['shipAddressUsageInd'] = '01'; // This transaction.
					} elseif ( $days_since_first_use < 30 ) {
						$account_data['shipAddressUsageInd'] = '02'; // Less than 30 days.
					} elseif ( $days_since_first_use < 60 ) {
						$account_data['shipAddressUsageInd'] = '03'; // 30-60 days.
					} else {
						$account_data['shipAddressUsageInd'] = '04'; // More than 60 days.
					}

					$account_data['shipAddressUsage'] = gmdate( 'Ymd', strtotime( $address_first_use ) );
				}
			}

			// Suspicious activity.
			$suspicious_activity                   = get_user_meta( $user_id, '_suspicious_activity', true );
			$account_data['suspiciousAccActivity'] = $suspicious_activity ? '02' : '01';

			// Payment account indicators.
			$payment_method_added = get_user_meta( $user_id, '_payment_method_added', true );
			if ( $payment_method_added ) {
				$days_since_payment_added = ( time() - strtotime( $payment_method_added ) ) / DAY_IN_SECONDS;

				if ( $days_since_payment_added < 1 ) {
					$account_data['paymentAccInd'] = '02'; // During this transaction.
				} elseif ( $days_since_payment_added < 30 ) {
					$account_data['paymentAccInd'] = '03'; // Less than 30 days.
				} elseif ( $days_since_payment_added < 60 ) {
					$account_data['paymentAccInd'] = '04'; // 30-60 days.
				} else {
					$account_data['paymentAccInd'] = '05'; // More than 60 days.
				}

				$account_data['paymentAccAge'] = gmdate( 'Ymd', strtotime( $payment_method_added ) );
			}
		} else {
			// Guest checkout indicators.
			$account_data['chAccAgeInd']           = '01'; // No account.
			$account_data['paymentAccInd']         = '01'; // No account.
			$account_data['suspiciousAccActivity'] = '01'; // No suspicious activity.
		}

		return array_filter( $account_data );
	}

	/**
	 * Prepare authentication information.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array
	 */
	private function prepare_authentication_info( array $order_data ): array {
		$auth_info = array();

		// Determine authentication method.
		if ( ! isset( $order_data['user_id'] ) || ! $order_data['user_id'] ) {
			$auth_info['threeDSReqAuthMethod'] = '01'; // No authentication.
		} elseif ( isset( $order_data['auth_method'] ) ) {
			switch ( $order_data['auth_method'] ) {
				case 'merchant_credentials':
					$auth_info['threeDSReqAuthMethod'] = '02';
					break;
				case 'federated':
					$auth_info['threeDSReqAuthMethod'] = '03';
					break;
				case 'issuer_credentials':
					$auth_info['threeDSReqAuthMethod'] = '04';
					break;
				case 'third_party':
					$auth_info['threeDSReqAuthMethod'] = '05';
					break;
				case 'fido':
					$auth_info['threeDSReqAuthMethod'] = '06';
					break;
				case 'fido_signed':
					$auth_info['threeDSReqAuthMethod'] = '07';
					break;
				case 'src':
					$auth_info['threeDSReqAuthMethod'] = '08';
					break;
				default:
					$auth_info['threeDSReqAuthMethod'] = '01';
			}

			if ( isset( $order_data['auth_timestamp'] ) ) {
				$auth_info['threeDSReqAuthTimestamp'] = gmdate( 'YmdHi', strtotime( $order_data['auth_timestamp'] ) );
			}

			if ( isset( $order_data['auth_data'] ) ) {
				$auth_info['threeDSReqAuthData'] = $order_data['auth_data'];
			}
		}

		return array_filter( $auth_info );
	}

	/**
	 * Prepare prior authentication information.
	 *
	 * @param array $order_data Order and customer data.
	 * @return array
	 */
	private function prepare_prior_auth_info( array $order_data ): array {
		$prior_auth = array();

		if ( isset( $order_data['prior_auth'] ) ) {
			switch ( $order_data['prior_auth'] ) {
				case 'frictionless':
					$prior_auth['threeDSReqPriorAuthMethod'] = '01';
					break;
				case 'challenge':
					$prior_auth['threeDSReqPriorAuthMethod'] = '02';
					break;
				case 'avs':
					$prior_auth['threeDSReqPriorAuthMethod'] = '03';
					break;
				case 'other':
					$prior_auth['threeDSReqPriorAuthMethod'] = '04';
					break;
			}

			if ( isset( $order_data['prior_auth_id'] ) ) {
				$prior_auth['threeDSReqPriorRef'] = $order_data['prior_auth_id'];
			}

			if ( isset( $order_data['prior_auth_timestamp'] ) ) {
				$prior_auth['threeDSReqPriorAuthTimestamp'] = gmdate( 'YmdHi', strtotime( $order_data['prior_auth_timestamp'] ) );
			}

			if ( isset( $order_data['prior_auth_data'] ) ) {
				$prior_auth['threeDSReqPriorAuthData'] = $order_data['prior_auth_data'];
			}
		}

		return array_filter( $prior_auth );
	}

	/**
	 * Process 3DS response.
	 *
	 * @param array $response Gateway response.
	 * @return array Processed 3DS data.
	 * @throws NovaBankaIPGException When the liability shift indicator is missing.
	 */
	public function process_3ds_response( array $response ): array {
		if ( ! isset( $response['liability'] ) ) {
			throw new NovaBankaIPGException( 'Missing liability shift indicator' );
		}
		$threeds_data = array(
			'liability_shift' => 'Y' === $response['liability'],
			'eci'             => $response['eci'] ?? null,
			'cavv'            => $response['cavv'] ?? null,
			'xid'             => $response['xid'] ?? null,
		);
		// Store authentication results if available.
		if ( 'VPAS' === $response['payinst'] && isset( $response['eci'] ) ) {
			$this->store_authentication_result( $response );
		}

		return array_filter( $threeds_data );
	}

	/**
	 * Get transaction count for user
	 *
	 * @param int    $user_id Time period.
	 * @param string $period  Time period.
	 * @return int
	 */
	private function get_transaction_count( int $user_id, string $period ): int {
		$since_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$period}" ) );

		$args = array(
			'customer_id'  => $user_id,
			'date_created' => '>' . $since_date,
			'return'       => 'ids',
		);

		$orders = wc_get_orders( $args );

		return count( $orders );
	}

	/**
	 * Get first use date of shipping address.
	 *
	 * @param int   $user_id Address to check.
	 * @param array $address Address to check.
	 * @return string|null
	 */
	private function get_address_first_use( int $user_id, array $address ): ?string {
		$address_hash = md5( wp_json_encode( $address ) );

		$args = array(
			'customer_id' => $user_id,
			'meta_key'    => '_shipping_address_hash',
			'meta_value'  => $address_hash,
			'orderby'     => 'date',
			'order'       => 'ASC',
			'return'      => 'ids',
		);

		$orders = wc_get_orders( $args );

		if ( empty( $orders ) ) {
			return null;
		}

		$order = wc_get_order( reset( $orders ) );
		return $order ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null;
	}

	/**
	 * Store authentication result.
	 *
	 * @param array $response Gateway response.
	 * @return void
	 */
	private function store_authentication_result( array $response ): void {
		if ( ! isset( $response['trackid'] ) ) {
			return;
		}

		$order_id = $response['trackid'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$this->logger->error(
				'Order not found for 3DS authentication storage',
				array(
					'track_id' => $response['trackid'],
				)
			);
			return;
		}

		// Store essential 3DS data.
		$auth_data = array(
			'eci'            => $response['eci'] ?? '',
			'cavv'           => $response['cavv'] ?? '',
			'xid'            => $response['xid'] ?? '',
			'liability'      => $response['liability'] ?? 'N',
			'card_type'      => $response['cardtype'] ?? '',
			'auth_timestamp' => current_time( 'mysql' ),
		);

		$order->update_meta_data( '_3ds_authentication', $auth_data );
		$order->add_order_note(
			sprintf(
				'Card authentication completed. ECI: %s, Liability Shift: %s',
				$auth_data['eci'],
				'Y' === $auth_data['liability'] ? 'Yes' : 'No'
			)
		);
	}
}
