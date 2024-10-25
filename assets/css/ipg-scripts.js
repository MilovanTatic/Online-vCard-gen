// assets/js/ipg-scripts.js

(function($) {
    'use strict';

    const NovaBankaIPG = {
        init: function() {
            this.form = $('form.checkout');
            this.submitButton = $('button#place_order');
            this.loadingOverlay = $('.ipg-loading-overlay');

            this.initializeEvents();
        },

        initializeEvents: function() {
            // Handle form submission
            this.form.on('checkout_place_order_novabankaipg', this.handleSubmit.bind(this));

            // Handle HPP return
            if (window.location.href.indexOf('novabankaipg-return') > -1) {
                this.handleReturn();
            }
        },

        handleSubmit: function() {
            this.showLoading();
            return true; // Allow form submission
        },

        showLoading: function() {
            this.loadingOverlay.addClass('active');
            this.submitButton.prop('disabled', true);
        },

        hideLoading: function() {
            this.loadingOverlay.removeClass('active');
            this.submitButton.prop('disabled', false);
        },

        handleReturn: function() {
            // Handle return from HPP
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('payment_status');

            if (status === 'success') {
                this.showMessage('Payment completed successfully.', 'success');
            } else if (status === 'cancel') {
                this.showMessage('Payment was cancelled.', 'error');
            } else if (status === 'error') {
                this.showMessage('Payment failed. Please try again.', 'error');
            }
        },

        showMessage: function(message, type) {
            const messageClass = type === 'success' ? 'ipg-status-success' : 'ipg-status-error';
            const messageHtml = `<div class="ipg-status-message ${messageClass}">${message}</div>`;
            
            $('.woocommerce-notices-wrapper').first().html(messageHtml);
            $('html, body').animate({
                scrollTop: $('.woocommerce-notices-wrapper').first().offset().top - 100
            }, 500);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        NovaBankaIPG.init();
    });

})(jQuery);