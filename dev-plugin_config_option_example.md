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
