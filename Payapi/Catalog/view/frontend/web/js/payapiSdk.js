(function (window) {

    // declare
    var payapiSdk = function () {
      var key = null;
      this.key = function() { key };
      this.publicId = function() { publicId };

      // load crypto-js libraries
      var imported = document.createElement('script');
      imported.src = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/core.js';
      document.head.appendChild(imported);

      var imported2 = document.createElement('script');
      imported2.src = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/enc-base64.js';
      document.head.appendChild(imported2);

      var imported3 = document.createElement('script');
      imported3.src = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/hmac-sha512.js';
      document.head.appendChild(imported3);
    };

    // sdk init() function
    payapiSdk.prototype.configure = function (publicId, key) {
      if(!validatePublicIdFormat(publicId)) {
        console.warn('Incorrect public Id');
        return ;
      } else if(!validateKeyFormat(key)) {
        console.warn('Key format is wrong');
        return ;
      } else {
        this.key = key; // Your api key
        this.publicId = publicId; // Your publicId
      }
    };

    payapiSdk.prototype.addSecurePaymentButtonToDiv = function(jsonObject, divClass, buttonText) {
      if (!validatePublicIdFormat(this.publicId) || !validateKeyFormat(this.key)) {
        console.warn('Configure your public Id and key before assing the payment button');
        return false;
      } else if(!validateJson(jsonObject)) {
        console.warn('The JSON file format is wrong');
        return false;
      } else {
        var key = this.key;
        var publicId = this.publicId;

        var button = document.createElement('input');
        button.type = 'image';
        button.content = buttonText ? buttonText : '';
        button.setAttribute('src', 'https://staging-input.payapi.io/pay-now.png');
        button.addEventListener('click', function() {
          initSecureform(jsonObject, key, publicId);
        }, true);

        var div = document.getElementById(divClass);
        if (div) {
          div.appendChild(button);
        } else {
          console.error('Secure payment button could not be assinged to an unexistent div id');
        }
      }
    };

    function base64url(source) {
      // Encode in classical base64
      encodedSource = CryptoJS.enc.Base64.stringify(source);
      // Remove padding equal characters
      encodedSource = encodedSource.replace(/=+$/, '');
      // Replace characters according to base64url specifications
      encodedSource = encodedSource.replace(/\+/g, '-');
      encodedSource = encodedSource.replace(/\//g, '_');

      return encodedSource;
    }

    function initSecureform(jsonObject, key, publicId) {
      var header = { "alg": "HS512", "typ": "JWT" };

      var stringifiedHeader = CryptoJS.enc.Utf8.parse(JSON.stringify(header));
      var encodedHeader = base64url(stringifiedHeader);

      var stringifiedData = CryptoJS.enc.Utf8.parse(JSON.stringify(jsonObject));
      var encodedData = base64url(stringifiedData);
      var token = encodedHeader + "." + encodedData;
      var signature = CryptoJS.HmacSHA512(token, key);
      signature = base64url(signature);

      var signedToken = token + "." + signature;      
      var form = document.createElement('form');
      form.style.display = 'none';
      form.setAttribute('method', 'POST');
      form.setAttribute('action', 'https://input.payapi.io/v1/secureform/'+ publicId);
      form.setAttribute('enctype', 'application/json');

      var input = document.createElement('input');
      input.name = 'data';
      input.type = 'text';
      input.setAttribute('value', signedToken);

      form.appendChild(input);
      document.getElementsByTagName('body')[0].appendChild(form);
      form.submit();

      return false;
    }

    function validateKeyFormat(key) {
      if (typeof(key) === 'undefined' || key.length !== 32) {
        return false;
      }
      return true;
    }

    function validatePublicIdFormat(publicId) {
      if (typeof(publicId) === 'undefined' || publicId.length < 6 || publicId.length > 50) {
        return false;
      } else if (!(/^([a-z])[a-z0-9-_]{5,49}$/).test(publicId)) {
        return false;
      }
      return true;
    }

    function validateJson(jsonObject) {
      try {
        var json = JSON.parse(JSON.stringify(jsonObject));
        return true;
      } catch (err) {
        return false;
      }
    }

    // define your namespace myApp
    window.payapiSdk = new payapiSdk();

})(window, undefined);
