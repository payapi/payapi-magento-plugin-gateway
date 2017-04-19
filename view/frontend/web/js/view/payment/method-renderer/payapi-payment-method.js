/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'mage/url'
    ],
    function (
        $,
        Component,
        additionalValidators,
        quote,
        customerData,
        urlBuilder
    ) {
        'use strict';

        return Component.extend(
            {
            defaults: {
                template: 'Payapi_CheckoutPayment/payment/payapi-payment-method',
                
            },

            /** Redirect to payapi */
            continueToPayApi: function () {
                if (additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    console.log("continueToPayApi: "+ quote.paymentMethod().method);
                   /* setPaymentMethodAction(this.messageContainer).done(
                        function () {*/
                            console.log("setPaymentMethodAction DONE");
                            customerData.invalidate(['cart']);
                            var order = quote.totals();
                            var quoteId = window.checkoutConfig.quoteItemData[0].quote_id;
                            var shippingMethod = quote.shippingMethod();
                            
                            var jsonProduct = null;
                            if (shippingMethod != null && typeof shippingMethod.carrier_code != 'undefined') {    
                                var vatPercentage = 0;
                                if(shippingMethod.price_excl_tax != 0){                            
                                    vatPercentage = parseFloat((shippingMethod.price_incl_tax-shippingMethod.price_excl_tax)*100/shippingMethod.price_excl_tax);
                                }
                                jsonProduct = {
                                    "id": shippingMethod.carrier_code+"_"+shippingMethod.method_code,
                                    "quantity" : 1,
                                    "title" : shippingMethod.carrier_title,
                                    "priceInCentsIncVat" : Math.round(shippingMethod.price_incl_tax*100),
                                    "priceInCentsExcVat" : Math.round(shippingMethod.price_excl_tax*100),
                                    "vatInCents" : Math.round((shippingMethod.price_incl_tax-shippingMethod.price_excl_tax)*100),
                                    "vatPercentage" : vatPercentage
                                };
                            }
                            
                            var address = quote.shippingAddress();
                            var jsonAddress = null;
                            if (address != null && address.firstname != null){
                                var street = '';
                                if (address.street && address.street.length > 0)
                                    street = address.street[0];
                                jsonAddress = {
                                    'firstname': address.firstname, //address Details
                                    'lastname' :address.lastname,
                                    'street' : street,
                                    'city' : address.city,
                                    'country_id' : address.countryId,
                                    'region' : address.regionCode,
                                    'postcode' : address.postcode,
                                    'telephone' : '0',
                                    'fax' : '0',
                                    'save_in_address_book' : 0
                                };
                            }
                            
                            var jsonData = {
                                "referenceQuoteId": quoteId,
                                "ipaddress":""
                            };

                            if(jsonProduct != null){
                                jsonData["shippingProduct"] = jsonProduct;
                            }
                            if(jsonAddress != null){
                                jsonData["checkoutAddress"] = jsonAddress;
                            }

                            console.log(JSON.stringify(jsonData));

                            $.ajax(
                                {
                                showLoader: true,
                                type: "POST",
                                url: "/payapipages/index/secureformgenerator",
                                data: jsonData,
                                    success: function(object){
                                      var form = document.createElement('form');
                                      form.style.display = 'none';
                                      form.setAttribute('method', 'POST');
                                      form.setAttribute('action', object.url);
                                      form.setAttribute('enctype', 'application/json');

                                      var input = document.createElement('input');
                                      input.name = 'data';
                                      input.type = 'text';
                                      input.setAttribute('value', object.data);

                                      form.appendChild(input);
                                      document.getElementsByTagName('body')[0].appendChild(form);
                                      form.submit();
                                    }
                                }
                            );
                       /* }
                    );*/

                    return false;
                }
                                                
            }
            }
        );
    }
);