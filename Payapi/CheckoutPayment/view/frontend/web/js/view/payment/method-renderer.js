define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        Component,
        rendererList
    ) {
        'use strict';
        rendererList.push(
            {
                type: 'payapi_checkoutpayment_secure_form_post',
                component: 'Payapi_CheckoutPayment/js/view/payment/method-renderer/secure-form-post'
            }
        );

        return Component.extend({});
    }
);