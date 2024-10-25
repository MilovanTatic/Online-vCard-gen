// assets/js/ipg-admin.js

(function($) {
    'use strict';

    const NovaBankaIPGAdmin = {
        init: function() {
            this.initializeTooltips();
            this.handleTestMode();
            this.initializeValidation();
        },

        initializeTooltips: function() {
            $('.ipg-help-tip').tipTip({
                'attribute': 'data-tip',
                'fadeIn': 50,
                'fadeOut': 50,
                'delay': 200
            });
        },

        handleTestMode: function() {
            const testModeCheckbox = $('#woocommerce_novabankaipg_testmode');
            const credentialsSection = $('.ipg-credentials-section');

            testModeCheckbox.on('change', function() {
                if ($(this).is(':checked')) {
                    credentialsSection.before(
                        '<div class="ipg-test-mode-notice">' +
                        'Test mode is enabled - test credentials will be used.' +
                        '</div>'
                    );
                } else {
                    $('.ipg-test-mode-notice').remove();
                }
            });

            // Trigger on page load
            testModeCheckbox.trigger('change');
        },

        initializeValidation: function() {
            const form = $('form#mainform');

            form.on('submit', function(e) {
                const terminal_id = $('#woocommerce_novabankaipg_terminal_id').val();
                const terminal_password = $('#woocommerce_novabankaipg_terminal_password').val();
                const secret_key = $('#woocommerce_novabankaipg_secret_key').val();

                if ($('#woocommerce_novabankaipg_enabled').is(':checked')) {
                    if (!terminal_id || !terminal_password || !secret_key) {
                        e.preventDefault();
                        alert('Please provide all required credentials for the payment gateway.');
                        return false;
                    }
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        NovaBankaIPGAdmin.init();
    });

})(jQuery);