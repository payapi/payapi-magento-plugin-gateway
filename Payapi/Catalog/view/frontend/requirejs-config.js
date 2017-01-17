var config = {	
    map: {
        '*': {
            payapiSdk: 'Payapi_CheckoutPayment/js/payapi-sdk.min',
            fngrtouch: 'Payapi_Catalog/fngrsharesdk/touch.min',
            fngrui: 'Payapi_Catalog/fngrsharesdk/sdk-ui.min',
            fngrsdk: 'Payapi_Catalog/fngrsharesdk/sdk-controller.min'
        }        
    },
    shim: {
        'fngrtouch': {
        	deps: ['jquery']
        } ,
        'fngrui':  {
        	deps: ['jquery','fngrui']
        },
        'fngrsdk':  {
        	deps: ['jquery','fngtouch','fngrui']
        }
    }
};