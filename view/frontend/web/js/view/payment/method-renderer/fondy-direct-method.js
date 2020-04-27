define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Customer/js/customer-data',
        'Magento_Checkout/js/action/place-order',
        'Magento_Customer/js/model/customer',
        'Magento_Checkout/js/model/payment/additional-validators',
        'mage/url',
        '$checkout',
        'Magento_Checkout/js/model/quote',
        'mage/storage',
        'Magento_Checkout/js/model/url-builder',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function ($,
              Component,
              customerData,
              placeOrderAction,
              customer,
              additionalValidators,
              url,
              $checkout,
              quote,
              storage,
              urlApi,
              fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Fondy_Fondy/payment/fondy_direct',
                checkout: null,
                token: null,
                redirectUrl: 'checkout/onepage/success'
            },
            initialize: function () {
                this._super();
                this.fcheckout = $checkout('Api');
            },

            getCode: function () {
                return 'fondy_direct';
            },

            isActive: function () {
                return true;
            },

            validate: function () {
                var $form = jQuery('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            },
            placeOrder: function (data, event) {
                if (event) {
                    event.preventDefault();
                }
                var _data = this.getData();
                var self = this,
                    placeOrder,
                    emailValidationResult = customer.isLoggedIn(),
                    loginFormSelector = 'form[data-role=email-with-possible-login]';
                if (!customer.isLoggedIn()) {
                    $(loginFormSelector).validation();
                    emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
                }
                if (emailValidationResult && this.validate() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    fullScreenLoader.startLoader();

                    var customerEmail = $(loginFormSelector + ' input[name=username]').val();
                    if (customerEmail === undefined || customerEmail === '' || customerEmail === false) {
                        customerEmail = window.checkoutConfig.customerData.email;
                    }
                    var serviceUrl = urlApi.createUrl('/fondy/get-payment-token', {});
                    var Payload = {
                        cartId: quote.getQuoteId(),
                        method: this.item.method,
                        customerData: customerEmail
                    };
                    storage.post(
                        serviceUrl, JSON.stringify(Payload)
                    ).done(
                        function (data) {
                            var info = JSON.parse(data);
                            if (info.response.response_status && info.response.response_status === 'success') {
                                self.token = info.response.token;
                                self.processFondy(info.response.token, _data.additional_data, placeOrder);
                            } else {
                                fullScreenLoader.stopLoader();
                                self.isPlaceOrderActionAllowed(true);
                                self.messageContainer.addErrorMessage({message: 'Error code:' + info.response.error_code + ' ' + info.response.error_message});
                                return false;
                            }
                        }
                    ).fail(function (data) {
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                    });
                    return false;
                }
                return false;
            },
            /**
             *
             * @param token
             * @param ccdata
             * @param placeOrder
             */
            processFondy: function (token, ccdata, placeOrder) {
                var self = this;
                var r = {
                    payment_system: "card",
                    token: token,
                    card_number: ccdata.cc_number,
                    expiry_date: ccdata.cc_exp_month + ccdata.cc_exp_year,
                    cvv2: ccdata.cc_cid
                };
                self.fcheckout.scope(function () {
                    this.request("api.checkout.form", "request", r).done(function (e) {
                        self.fodnyPlaceOrder(placeOrder);
                        return true;
                    }).fail(function (e) {
                        if (e.data.error.code === 2009) {
                            self.fodnyPlaceOrder(placeOrder);
                            return true;
                        }
                        if (e.data.error)
                            self.messageContainer.addErrorMessage({message: e.data.error.message});
                        else
                            self.messageContainer.addErrorMessage({message: 'Payment status:' + e.data.response_status});
                        fullScreenLoader.stopLoader();
                        self.isPlaceOrderActionAllowed(true);
                        return false;
                    })
                });
            },
            fodnyPlaceOrder: function (placeOrder) {
                var self = this;
                placeOrder = placeOrderAction(self.getData(), self.messageContainer);
                $.when(placeOrder).fail(function () {
                    fullScreenLoader.stopLoader();
                    self.isPlaceOrderActionAllowed(true);
                }).done(
                    fullScreenLoader.stopLoader(),
                    self.afterPlaceOrder.bind(self)
                );
                return true;
            },
            /**
             * @returns {Boolean}
             */
            isShowLegend: function () {
                return true;
            },
            afterPlaceOrder: function () {
                fullScreenLoader.startLoader();
                window.location.replace(url.build(this.redirectUrl));
            }
        });
    }
);
