<?php
/*
 * Plugin Name: WooCommerce IPG Payment Gateway
 * Plugin URI: https://www.tasgroup.rs/
 * Description: Extends WooCommerce with the IPG Payment Gateway.
 * Author: TAS EE
 * Author URI: https://www.tasgroup.rs/
 * Version: 1.0.4
 *
 * History: 
 *  1.0.1
 *      - Basic purchase functionality
 *  1.0.2
 *  - Added purchaseInstallData
 *  - Added action_woocommerce_order_refunded
 *  1.0.3
 *  - Fixed action woocommerce_api_callback
 *  1.0.4
 *  - Increased Response Title size and fixed default value of response URL 
 *  1.0.5
 *  - Improved NOT CAPTURED error message presentation
 *
 */

add_action( 'woocommerce_thankyou', 'ipg_order_received_title', 1 );
function ipg_order_received_title( ) {
    
    $title = '';
    if (isset($_GET['result']) &&  ($_GET['result'] == 'CAPTURED' || $_GET['result'] == 'APPROVED')) {
        $title.='<div style="color:Green;font-size: 2em;padding-bottom: 0.9em;">';
        $title.='<label>Payment successful.</label><br>';
        $title.='<label>Result: ' . $_GET['result'] . '</label><br>';
        $title.='<label>Reference:'.$_GET['ref'].'</label><br>';
        $title.='</div>';
    } else if (isset($_GET['result']) && $_GET['result'] == 'NOT_CAPTURED') {
        $title.='<div style="color:Red;font-size: 2em;padding-bottom: 0.9em;">';
        $title.='<label>Payment Declined</label><br>';
        $title.='<label>Result: NOT CAPTURED</label><br>';
        $title.='<label>Response Code: '.$_GET['responseCode'].'</label><br>';
        if (isset($_GET['responseDescription'])) {
            $title.='<label>Response Description: '.$_GET['responseDescription'].'</label><br>';
        }
        $title.='<label>Reference: '.$_GET['ref'].'</label><br>';
        $title.='</div>';
    } else  if (isset($_GET['errorCode'])) {
        $title.='<div style="color:Red;font-size: 2em;padding-bottom: 0.9em;">';
        $title.='<label>Error :'.$_GET['errorCode'];
        if (isset($_GET['errorDesc'])) {
            $title.='-'.$_GET['errorDesc'].'';
        }
        $title.='</label>';
        $title.='</div>';
    } else {
        $title.='<div style="color:Red;font-size: 1.4em;padding-bottom: 0.9em;">';
        $title.='<label>Notification Error - Check RESPONSE URL</label><br>';
        $title.='<label>Please contact support</label><br>';
        $title.='</div>';
    }
    $title .= '<B>';
    
    echo $title;
}

add_filter('woocommerce_payment_gateways', 'ipg_gateway_class');
function ipg_gateway_class($gateways)
{
    $gateways[] = 'WC_IPG_POST_Gateway';
    return $gateways;
}

// Setup the responseURL interface
add_filter('query_vars', 'ipg_add_query_vars');

/**
 * Add the 'ipg_response_interface' query variable so WordPress
 * won't remove it.
 */
function ipg_add_query_vars($vars)
{
    $vars[] = "ipg_response_interface";
    return $vars;
}

add_action('woocommerce_before_thankyou', 'ipg_before_thankyou');

function ipg_before_thankyou($order_id)
{
    $has_order_id = isset($order_id);
    error_log('has_order_id ');
    error_log($has_order_id);
    if ($has_order_id != 1)
        return $order_id;
    
    error_log('Could perform something with Order ID: ');
    error_log($order_id);
    
    $order = new WC_Order($order_id);
    $order->set_payment_method_title('TUSAM');
    
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'ipg_init_gateway_class');

function ipg_init_gateway_class()
{
    
    /**
     * check for 'ipg_response_interface' query variable and do what you want if its there
     */
    add_action('woocommerce_api_callback', 'ipg_response_interface');

    function ipg_response_interface($template)
    {
        $WC_IPG_POST_Gateway = new WC_IPG_POST_Gateway();
        
        // Load basics
        require_once ('wp-load.php');
        
        $request_body = file_get_contents("php://input");
        error_log('Received JSON request:');
        error_log($request_body);
        
        if (! isset($request_body) || empty($request_body)) {
            error_log('Received Empty JSON:');
            error_log($request_body);
            return $template;
        }
        
        $request_json = json_decode($request_body);
        
        // json request, reply with json response
        header('Content-Type: application/json');
        
        // Process the order
        if (isset($request_json->type) && $request_json->type == 'valid') {
            error_log('IPG JSON Valid Message:');
            // Get vars
            $trackid = intval($request_json->trackid);
            // Create the Order object
            $order = new WC_Order($trackid);
            
            $order->set_transaction_id($request_json->paymentid);
            
            $result_url = $order->get_checkout_order_received_url();
            if (isset($request_json->result) && ($request_json->result == 'CAPTURED' || $request_json->result == 'APPROVED')) {
                $result_url .=  '&result='.$request_json->result.'&ref='.$request_json->ref;
                // Mark order as 'processing'
                $order->payment_complete();
                $order->add_order_note('Received Successful payment from IPG gateway, result: ' . $request_json->result . ', ref: ' . $request_json->ref, 'woocommerce_gateway_ipg');
                // log
                error_log('Received Payment successful from IPG gateway');
                // Order successful URL
            } else {
                $result_url .=  '&result=NOT_CAPTURED&responseCode='.$request_json->responsecode.'&ref='.$request_json->ref.'&responseDescription='.$request_json->responsedescription;
                // Mark order as 'failed'
                $order->update_status('failed', __('Received Payment Declined from IPG gateway , result: ' . $request_json->result . ', responseCode: ' . $request_json->responsecode . ', ref: ' . $request_json->ref.', responseDescription='.$request_json->responsedescription, 'woocommerce_gateway_ipg'));
                // log
                error_log('Received Payment Declined from IPG gateway ');
                // Order successful URL
            }
            // Command the redirection to the ThankYou page
            $message_verifier_fields_array = array(
                'PaymentNotificationResponse',
                '1',
                $request_json->paymentid,
                $WC_IPG_POST_Gateway->get_option('SecretKey'),
                $result_url
            );
            
            // load message verifier
            $msgVerifier = getMessageVerifier($message_verifier_fields_array);
            $successful_json_array = array(
                'paymentID' => $request_json->paymentid,
                'msgVerifier' => $msgVerifier,
                'msgName' => 'PaymentNotificationResponse',
                'version' => '1',
                'browserRedirectionURL' => $result_url
            );
            $response_json = json_encode($successful_json_array, JSON_PRETTY_PRINT);
            error_log('Sending JSON response:');
            error_log($response_json);
            echo $response_json;
        } else {
            error_log('IPG JSON Error Message:');
            error_log($request_json->errorCode);
            error_log($request_json->errorDesc);
            error_log($request_json->paymentid);
            error_log($request_json->trackid);
            
            wc_add_notice('IPG Response Error', 'error');
            wc_add_notice($request_json->errorDesc, 'error');
            
            // Get vars
            $trackid = intval($request_json->trackid);
            // Create the Order object
            $order = new WC_Order($trackid);
            // Mark as 'Processing'
            $order->update_status('failed', __('Received Error from IPG gateway payment, paymentid: ' . $request_json->paymentid . ', errorCode: ' . $request_json->errorCode . ', errorDesc: ' . $request_json->errorDesc, 'woocommerce_gateway_ipg'));
            
            $result_url = $order->get_checkout_order_received_url() . '&errorCode=' . $request_json->errorCode . '&errorDesc=' . $request_json->errorDesc;
            $message_verifier_fields_array = array(
                'PaymentNotificationResponse',
                '1',
                $request_json->paymentid,
                $WC_IPG_POST_Gateway->get_option('SecretKey'),
                // 'YXKZPOQ9RRLGPDED5D3PC5BJ',
                $result_url
            );
            
            // load message verifier
            $msgVerifier = getMessageVerifier($message_verifier_fields_array);
            
            $error_json_array = array(
                'paymentID' => $request_json->paymentid,
                'msgVerifier' => $msgVerifier,
                'msgName' => 'PaymentNotificationResponse',
                'version' => '1',
                'browserRedirectionURL' => $result_url
            );
            $response_json = json_encode($error_json_array, JSON_PRETTY_PRINT);
            error_log('Sending JSON response:');
            error_log($response_json);
            echo $response_json;
        }
        exit();

        return $template;
    }

    function getMessageVerifier($messageVerifierFields)
    {
        $messageVerifierBase = '';
        foreach ($messageVerifierFields as &$messageVerifierField) {
            $messageVerifierBase .= $messageVerifierField;
        }
        error_log('Message Verifier Base loaded: ');
        error_log($messageVerifierBase);
        
        $messageVerifierBase_hash = hash('sha256', $messageVerifierBase);
        error_log('Message Verifier Hash Hex loaded: ');
        error_log($messageVerifierBase_hash);
        
        $messageVerifierBase_hash_bytes = hex2bin($messageVerifierBase_hash);
        
        // Convert binary to base64
        $msgVerifier = base64_encode($messageVerifierBase_hash_bytes);
        error_log('Message Verifier Hash Base64 loaded: ');
        error_log($msgVerifier);
        return $msgVerifier;
    }

    class WC_IPG_POST_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor
         */
        public function __construct()
        {
            $this->id = 'asoftipg'; // payment gateway plugin ID

            $this->CheckoutIconUrl = $this->get_option('CheckoutIconUrl');
            
            if (empty($this->CheckoutIconUrl)) {
                // Set a default value
                $this->icon = plugin_dir_url(__FILE__) . '../ipg-gateway/assets/img/TASEE.png';
            } else {
                $this->icon = $this->CheckoutIconUrl;
            }
            
            $this->has_fields = false; // true in case you need a custom credit card form
            $this->method_title = 'IPG Gateway';
            $this->method_description = 'Description of IPG payment gateway'; // will be displayed on the options page
                                                                              
            // gateways can support subscriptions, refunds, saved payment methods,
                                                                              // but in this plugin we begin with simple payments
            $this->supports = array(
                'products'
            );
            
            // Method with all the options fields
            $this->init_form_fields();
            
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            
            $this->MessageType = $this->get_option('MessageType');
            $this->MessageVersion = $this->get_option('MessageVersion');
            $this->TerminalID = $this->get_option('TerminalID');
            $this->Password = $this->get_option('Password');
            $this->IPGURL = $this->get_option('IPGURL');
            $this->IPGSelect = $this->get_option('IPGSelect');
            $this->SecretKey = $this->get_option('SecretKey');
            $this->Action = $this->get_option('Action');
            $this->ResponseURL = $this->get_option('ResponseURL');
            $this->ErrorURL = $this->get_option('ErrorURL');
            $this->NotificationFormat = $this->get_option('NotificationFormat');
            $this->PaymentPageMode = $this->get_option('PaymentPageMode');
            $this->PaymentInstrument = $this->get_option('PaymentInstrument');
            $this->CardSHA2 = $this->get_option('CardSHA2');
            $this->PaymentTimeout = $this->get_option('PaymentTimeout');
            $this->Language = $this->get_option('Language');
            $this->PurchaseInstalData = $this->get_option('PurchaseInstalData');
            $this->CheckoutIconUrl = $this->get_option('CheckoutIconUrl');
            
            
            
            // This action hook saves the settings
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
            
            // We need custom JavaScript to obtain a token
            // add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            
            // You can also register a webhook here
            // add_action( 'woocommerce_api_IPG_webhook', array( $this, 'webhook' ) );
        }

        /**
         * Plugin options
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default' => 'Credit Card',
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default' => 'Pay with your credit card via our IPG payment gateway.'
                ),
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable IPG Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'MessageType' => array(
                    'title' => 'Message Type',
                    'type' => 'select',
                    'default' => 'VISEC / VIREC first transaction',
                    'class' => 'MessageType wc-enhanced-select',
                    'options' => array(
                        'MessageType' => 'VISEC / VIREC first transaction'
                    )
                ),
                'MessageVersion' => array(
                    'title' => 'Message Version',
                    'type' => 'select',
                    'default' => '1',
                    'class' => 'MessageVersion wc-enhanced-select',
                    'options' => array(
                        '1' => '1'
                    )),
                'TerminalID' => array(
                    'title' => 'Terminal ID:',
                    'type' => 'text',
                    'default' => '89110001'
                ),
                'Password' => array(
                    'title' => 'Password:',
                    'type' => 'password',
                    'default' => 'test1234'
                ),
                'IPGURL' => array(
                    'title' => 'IPG:',
                    'type' => 'text',
                    'default' => 'http://ipg-test:9080/IPGWeb/servlet/PaymentInitRequest'
                ),
                'SecretKey' => array(
                    'title' => 'Secret Key:',
                    'type' => 'text',
                    'default' => 'YXKZPOQ9RRLGPDED5D3PC5BJ'
                ),
                'Action' => array(
                    'title' => 'Action:',
                    'type' => 'select',
                    'default' => '1',
                    'class' => 'Action wc-enhanced-select',
                    'options' => array(
                        '1' => 'PURCHASE',
                        '4' => 'AUTHORIZATION'
                    )
                ),
                'ResponseURL' => array(
                    'title' => 'RESPONSE URL:',
                    'type' => 'text',
                    'default' => get_home_url() . '/wc-api/CALLBACK/?ipg_response_interface=1'
                ),
                'ErrorURL' => array(
                    'title' => 'ERROR URL:',
                    'type' => 'text',
                    'default' => get_home_url() . '/wc-api/CALLBACK/?ipg_response_interface=1'
                ),
                'NotificationFormat' => array(
                    'title' => 'Notiication Format:',
                    'type' => 'select',
                    'default' => 'json',
                    'class' => 'NotificationFormat wc-enhanced-select',
                    'options' => array(
                        'json' => 'JSON'
                    )
                ),
                'PaymentPageMode' => array(
                    'title' => 'Payment Page Mode:',
                    'type' => 'select',
                    'default' => '0',
                    'class' => 'PaymentPageMode wc-enhanced-select',
                    'options' => array(
                        '0' => 'STANDARD'
                    )
                ),
                'PaymentInstrument' => array(
                    'title' => 'Payment Instrument:',
                    'type' => 'text',
                    'default' => ''
                ),
                'CardSHA2' => array(
                    'title' => 'CARD SHA2:',
                    'type' => 'select',
                    'default' => 'Y',
                    'class' => 'CardSHA2 wc-enhanced-select',
                    'options' => array(
                        'Y' => 'Yes',
                        'N' => 'No'
                    )
                ),
                'PaymentTimeout' => array(
                    'title' => 'Payment Timeout:',
                    'type' => 'text',
                    'default' => '30'
                ),
                'Language' => array(
                    'title' => 'Language:',
                    'type' => 'text',
                    'default' => 'USA'
                ),
                'PurchaseInstalData' => array(
                    'title' => 'Installment Number: ',
                    'type' => 'text',
                    'default' => ''
                ),
                
                'CheckoutIconUrl' => array(
                    'title' => 'Checkout Icon URL: ',
                    'type' => 'text',
                    'description' => 'Insert URL of your Checkout Icon, or leave empty to use the default one.',
                    'default' => ''
                )
            );
        }

        public function process_payment($order_id)
        {
            global $woocommerce;
            
            // we need it to get any order detailes
            $order = new WC_Order($order_id);
            
            $args = array(
                "msgName" => "PaymentInitRequest",
                'version' => $this->MessageVersion,
                'id' => $this->TerminalID,
                'password' => $this->Password,
                
                'msgVerifier' => "",
                'langId' => $this->Language,
                
                'CartContent' => $this->CartContent, // JSON [complex]
                                                     
                // 'buyerFirstName' => $this->FirstName,
                                                     // 'buyerFirstName' => "",
                                                     // 'buyerLastName' => "",
                                                     // 'buyerUserId' => "",
                                                     // 'buyerPhoneNumber' => "",
                                                     // 'buyeremailaddress' => "",
                                                     // 'clientIpAddress' => "",
                                                     // 'clientUserAgent' => "",
                                                     // 'clientHttpHeaders' => "",
                                                     
                // 'shippingInfo' => "", // JSON
                                                     // 'billingInfo' => "", // JSON
                                                     
                // 'acctType' => "",
                                                     // 'accountInfo' => "", // JSON
                                                     // 'authenticationInfo' => "", // JSON
                                                     // 'priorAuthenticationInfo'=> "", // JSON
                
                'action' => $this->Action,
                'recurAction' => "",
                'recurContractId' => "",
                'responseURL' => $this->ResponseURL, // responseURL
                'errorURL' => $this->ErrorURL, // errorURL
                'currencycode' => $order->get_currency(),
                'amt' => $order->get_total(),
                'trackid' => $order_id,
                'cardSHA2' => $this->CardSHA2,
                'paymentTimeout' => $this->PaymentTimeout,
                //'pymnDscr' => $this->PaymentDescription,
                'notificationFormat' => $this->NotificationFormat,
                'paymentPageMode' => $this->PaymentPageMode,
                'payinst' => $this->PaymentInstrument,
                'purchaseInstalData' => $this->PurchaseInstalData,
            );
            
            if (! function_exists('write_log')) {

                function write_log($log)
                {
                    if (true === WP_DEBUG) {
                        if (is_array($log) || is_object($log)) {
                            error_log(print_r($log, true));
                        } else {
                            error_log($log);
                        }
                    }
                }
            }
            
            $message_verifier_fields_array = array(
                $args['msgName'],
                $args['version'],
                $args['id'],
                $args['password'],
                $args['amt'],
                $args['trackid'],
                '',
                $this->SecretKey,
                ''
            );
            
            // load message verifier
            $args['msgVerifier'] = getMessageVerifier($message_verifier_fields_array);
            
            $args['errorURL'] = $order->get_checkout_order_received_url();
            /*
             * Your API interaction could be built with wp_remote_post()
             */
            // $response = wp_remote_post( '{payment processor endpoint}', $args );
            
            $ipg_url = $this->IPGURL;
            write_log('Sending request, url: ');
            write_log($ipg_url);
            
            $request_preety_json = json_encode($args, JSON_PRETTY_PRINT);
            write_log('request_preety_json: ');
            write_log($request_preety_json);
            
            $request_json = json_encode($args);
            write_log('request_json: ');
            write_log($request_json);
            
            // $url = 'localhost:9082/IPGWeb/servlet/PaymentInitRequest';
            $response = wp_remote_post($ipg_url, array(
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8'
                ),
                'body' => $request_json,
                'method' => 'POST',
                'data_format' => 'body'
            ));
            
            if (is_wp_error($response) && ! empty($response->errors)) {
                wc_add_notice('HTTP Response Error', 'error');
                wc_add_notice($response->get_error_message(), 'error');
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            write_log('Loaded response code, response_code: ');
            write_log($response_code);
            if (! in_array($response_code, array(
                200,
                201
            ))) {
                wc_add_notice('HTTP Status Error', 'error');
                wc_add_notice($response_code, 'error');
                return;
            }
            
            $response_body = wp_remote_retrieve_body($response);
            write_log('Loaded response body, response_body: ');
            write_log($response_body);
            
            $response_json = json_decode($response_body);
            if (json_last_error() > JSON_ERROR_NONE) {
                wc_add_notice('Response JSON Error:', 'error');
                wc_add_notice(json_last_error_msg(), 'error');
                return;
            }
            
            write_log('Loadded JSON response, response_preety_json: ');
            $response_preety_json = json_encode($response_json, JSON_PRETTY_PRINT);
            write_log($response_preety_json);
            
            // it could be different depending on your payment processor
            if ($response_json->type == 'valid') {
                
                $response_url = $response_json->browserRedirectionURL;
                $response_url .= "?PaymentID=";
                $response_url .= $response_json->paymentid;
                
                if (! filter_var($response_url, FILTER_VALIDATE_URL)) {
                    wc_add_notice('Invalid IPG Response Url:', 'error');
                    wc_add_notice($response_url, 'error');
                    return;
                }
                
                // Mark as on-hold (we're awaiting the cheque)
                $order->update_status('on-hold', __('Awaiting IPG payment', 'ipg_plugin'));
                
                // // Reduce stock levels
                // $order->reduce_order_stock();
                
                // // Remove cart
                // $woocommerce->cart->empty_cart();
                
                // Redirect to the thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $response_url
                );
            } else {
                wc_add_notice('IPG Response Error:', 'error');
                wc_add_notice($response_json->errorCode, 'error');
                wc_add_notice($response_json->errorDesc, 'error');
                return;
            }
                        
        }
        
         
    }
    
    
    // REFOUND CALL 1!
    
    // add the action
    add_action( 'woocommerce_order_refunded', 'action_woocommerce_order_refunded', 10, 2 );
    
    // Do the magic line 659
    function action_woocommerce_order_refunded( $order_id, $refund_id )
    {
        // Your code here
        error_log('REFOUND CALL >>> POST');

        error_log('Order ID: ');
        error_log($order_id);
        
        error_log('$refund_id: ');
        error_log($refund_id);        
        
        // we need it to get any order detailes
        $order = new WC_Order($order_id);
        
        $ipg_url =  'http://ipg-test:33666/IPGWeb/servlet/PaymentInitRequest';
        
        //  Sta treba da posaljem, i kako to da dohvatim i spakujem            <<<<<
        
        global $woocommerce;
        $order = new WC_Order($order_id);
        $WC_IPG_POST_Gateway = new WC_IPG_POST_Gateway();
        
       
        $a = array ($WC_IPG_POST_Gateway->get_form_fields());
        $b = $a['SecretKey'][0]->SecretKey;
        $b = 'YXKZPOQ9RRLGPDED5D3PC5BJ';
        
        error_log(' SecretKey == ');
        error_log( $b );
        
        //init_form_fields();

        $args = array(
            'msgName'           => 'FinancialRequest',               
            'version'           => '1',                              
            'id'                => '89110001',     
            'password'          => 'test1234',                       
            'action'            => '2',                             
            'amt'               => '0,01',                           
            'currencycode'      => '840',                            
            'trackid'           => 'CTV-TEST-PureBuy-1',              
            'tranid'            => '980026872121022345', 
            'udf1'               => 'AA',
            'udf2'               => 'BB',
            'udf3'               => 'CC',
            'udf4'               => 'DD',
            'udf5'               => 'EE',
        );
        
        $refund_parameters_message_verifier_fields_array = array(
            $args['msgName'],
            $args['version'],
            $args['id'],
            $args['password'],
            $args['amt'],
            $args['trackid'],
            '',
            $b,
            ''
        );
        
        // Just print to LOG
        $request_preety_json = json_encode($args, JSON_PRETTY_PRINT);
        error_log('request_preety_json: ');
        error_log($request_preety_json);
        
        $refund_parameters_message_verifier_preety_json = json_encode($args, JSON_PRETTY_PRINT);
        error_log('request_preety_json: ');
        error_log($refund_parameters_message_verifier_preety_json);
        
        // load message verifier
        $msgVerifier = getMessageVerifier($refund_parameters_message_verifier_fields_array);
        $args['msgVerifier'] = $msgVerifier;

        error_log(' msgVerifier== ');
        error_log( $msgVerifier );
                
        $json_to_go = json_encode($args);
        error_log($json_to_go);
        
        $response = wp_remote_post($ipg_url, array(
            'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
            'body'        => json_encode($args),
            'method'      => 'POST',
            'data_format' => 'body',
        ));
        
        error_log('DUMMY TEST NOVAK, received data: ');
        
        error_log(' transactionId== ');
        error_log($response->transactionId);
        
        error_log(' order_number== ');
        error_log($response->order_number);
        

    }
    

    
}