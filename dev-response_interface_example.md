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