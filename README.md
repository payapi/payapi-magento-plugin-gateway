# PayApi Plugin for Magento 2

Magento payment method implementation of [PayApi Secure Form](https://payapi.io/apidoc/#api-Payments-PostSecureForm).

## Contents

Includes an easy-to-use PayApi extension for any Magento 2 webshop (plugin).
In order to use the extension, please register for a free [PayApi user account](https://input.payapi.io)

## Server Requirements

* [Magento CE](http://devdocs.magento.com/magento-system-requirements.html) 2.0 or higher. This plugin has been validated to work against the 2.1.0 Community Edition release.
* [PHP](http://us2.php.net/downloads.php) 5.6 or higher. This plugin will not work on PHP 5.5 and below.

## Installation
#### Method 1: Install Via Shell Script
* Download the script 'install-payapi-extension.sh'
* Run the script:

```bash
sh install-payapi-extension.sh  <magento_user@remote_server> <magento_home_directory>
```

#### Method 2: Manual installation in server
* Clone this repository in your local
* Copy the Payapi and i18n folder inside your magento-home-directory/app/code
* Upgrade modules list
```bash
php bin/magento setup:upgrade
```
* Deploy new modules
```bash
php bin/magento setup:di:compile
```

## Configuration
* Go to the Magento admin, open the menu option Stores > Configuration > Sales > Payment Methods
* Open the PayApi extension section and type your PayApi PublicId and your PayApi API key (You can get your publicId and API key from [here](https://input.payapi.io/#!/backoffice/subscription))
* Select your default Shipping Method for the "Instant Buy" functionality (It might require to create a new shipping method if there are no methods)
* Save Config

## Questions?

Please contact support@payapi.io for any questions.
