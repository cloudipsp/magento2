define(
    [
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/select-payment-method',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/checkout-data',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function ($,
              quote,
              urlBuilder,
              storage,
              customerData,
              Component,
              placeOrderAction,
              selectPaymentMethodAction,
              customer,
              checkoutData,
              additionalValidators,
              url,
              fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Fondy_Fondy/payment/fondy'
            },
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }
                var self = this,
                    placeOrder;
                if (this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    fullScreenLoader.startLoader();
                    placeOrder = placeOrderAction(this.getData(), false, this.messageContainer);

                    $.when(placeOrder).fail(function () {
                        self.isPlaceOrderActionAllowed(true);
                    }).done(function (id, status) {
                        self.afterPlaceOrder(id);
                    }).always(function(){
                        fullScreenLoader.stopLoader();
                    });
                    return true;
                }
                return false;
            },

            selectPaymentMethod: function () {
                selectPaymentMethodAction(this.getData());
                checkoutData.setSelectedPaymentMethod(this.item.method);
                return true;
            },

            afterPlaceOrder: function (id) {
                window.location.replace(url.build('fondy/url/submit?nocache=' + (new Date().getTime()) + '&order=' + id));
            }

        });
    }
);
