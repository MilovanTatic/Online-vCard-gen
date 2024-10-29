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
