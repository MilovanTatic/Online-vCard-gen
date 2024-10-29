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