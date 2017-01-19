/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Paypal/js/action/set-payment-method',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/customer-data',
        'mage/url',
        'payapiSdk'
    ],
    function (
        $,
        Component,
        setPaymentMethodAction,
        additionalValidators,
        quote,
        customerData, 
        urlBuilder
    ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Payapi_CheckoutPayment/payment/secure-form-post',
                
            },


            /** Redirect to paypal */
            continueToPayApi: function () {
                if (additionalValidators.validate()) {
                    //update payment method information if additional data was changed
                    this.selectPaymentMethod();
                    setPaymentMethodAction(this.messageContainer).done(
                        function () {
                            customerData.invalidate(['cart']);
                    var order = quote.totals();                    
                    var currencyCode = order.quote_currency_code;
                    var baseExclTax = order.base_subtotal_with_discount;
                    var taxAmount = order.tax_amount;      
                    var shippingAmount = order.shipping_amount;
                    var totalOrdered = order.base_grand_total;
                    var quoteId = window.checkoutConfig.quoteItemData[0].quote_id;
                    var shippingMethod = quote.shippingMethod();
                    var jsonOrder = {
                        "sumInCentsIncVat" : Math.round(totalOrdered*100),
                        "sumInCentsExcVat" : Math.round((baseExclTax + shippingAmount)*100),
                        "vatInCents" : Math.round(taxAmount*100),
                        "currency" : currencyCode,
                        "referenceId" : quoteId
                    };
                    var prods = quote.getItems();
                    var jsonProducts = [];

                    for (var i = 0; i < prods.length; i++) {
                        var item = prods[i];
                        jsonProducts.push({
                            "id": item.product_id,
                            "quantity" : item.qty,
                            "title" : item.name,
                            "priceInCentsIncVat" : Math.round(item.row_total_incl_tax*100),
                            "priceInCentsExcVat" : Math.round(item.row_total*100),
                            "vatInCents" : Math.round(item.tax_amount*100),
                            "vatPercentage" : parseFloat(item.tax_percent),
                            "imageUrl" : item.thumbnail,
                            "extraData" : "item="+window.checkoutConfig.quoteItemData[i].item_id
                        });   
                    }
                    //Shipping method as item

                    jsonProducts.push({
                        "id": shippingMethod.carrier_code+"_"+shippingMethod.method_code,
                        "quantity" : 1,
                        "title" : shippingMethod.carrier_title,
                        "priceInCentsIncVat" : Math.round(shippingMethod.price_incl_tax*100),
                        "priceInCentsExcVat" : Math.round(shippingMethod.price_excl_tax*100),
                        "vatInCents" : Math.round((shippingMethod.price_incl_tax-shippingMethod.price_excl_tax)*100),
                        "vatPercentage" : parseFloat((shippingMethod.price_incl_tax-shippingMethod.price_excl_tax)*100/shippingMethod.price_excl_tax) 
                    });
                    
                    var address = quote.billingAddress();
                    var jsonConsumer = { "email" : quote.guestEmail };//, "mobilePhoneNumber" :  address.telephone };
                    var jsonAddress = {
                        "recipientName" : address.firstname + " " + address.lastname,               
                        "streetAddress" : address.street[0],
                        "streetAddress2" : address.street[1],
                        "postalCode" : address.postcode,
                        "city" : address.city,
                        "stateOrProvince" : address.region,
                        "countryCode" : address.countryId
                    };
           

                    //Return URLs
                    var jsonReturnUrls = {
                        "success" : urlBuilder.build("payapipages/returns/success") ,
                        "cancel" : urlBuilder.build("payapipages/returns/cancelled") ,
                        "failed" : urlBuilder.build("payapipages/returns/failure")     
                    };

                    //Callbacks
                    var callbackUrl = urlBuilder.build("rest/V1/payapipages/callback");
                    var jsonCallbacks = {
                        "processing" : callbackUrl,
                        "success" : callbackUrl,
                        "failed" : callbackUrl,
                        "chargeback" : callbackUrl
                    };

                    var jsonData = {
                        "order" : jsonOrder,
                        "products" : jsonProducts, 
                        "consumer" : jsonConsumer, 
                        "shippingAddress" : jsonAddress, 
                        "returnUrls" : jsonReturnUrls,
                        "callbacks" : jsonCallbacks
                    };

                    console.log(JSON.stringify(jsonData));

                    payapiSdk.configure(window.checkoutConfig.payment.customPayment.payapi_public_id, window.checkoutConfig.payment.customPayment.payapi_api_key);
                    payapiSdk.postData(jsonData);    
                        }
                    );

                    return false;
                }
                                                
            }
        });
    }
);