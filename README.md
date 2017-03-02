# PayApi Plugin - Payment Gateway Core for Magento 2

Magento payment method implementation of [PayApi Secure Form](https://payapi.io/apidoc/#api-Payments-PostSecureForm).

## Contents

Includes a new payment gateway for the checkout process, the PayApi Online Payments system.
In order to use the payment gateway, please register for a free [PayApi user account](https://input.payapi.io)

## Server Requirements

* [Magento CE](http://devdocs.magento.com/magento-system-requirements.html) 2.0 or higher. This plugin has been validated to work against the 2.1.0 Community Edition release.
* [PHP](http://us2.php.net/downloads.php) 5.6 or higher. This plugin will not work on PHP 5.5 and below.
* [Composer](http://devdocs.magento.com/guides/v2.0/install-gde/prereq/integrator_install_composer.html)

## Installation
#### Method 1: Install Via Shell Script
* Download the script 'install-payapi-extension.sh'
* Run the script:

```bash
sh install-payapi-extension.sh  <magento_user@remote_server> <magento_home_directory>
```

#### Method 2: Manual installation in server with composer
* Run the composer require command
```bash
composer require payapi/magento-plugin-gateway
```
* Upgrade modules list
```bash
php bin/magento setup:upgrade
```
* Deploy new modules
```bash
php bin/magento setup:di:compile
```
* Deploy static content
```bash
php bin/magento setup:static-content:deploy en_GB fi_FI es_ES
```

## Configuration
* Go to the Magento admin, open the menu option Stores > Configuration > Sales > Payment Methods
* Open the PayApi extension section and type your PayApi PublicId and your PayApi API key (You can get your publicId and API key from [here](https://input.payapi.io/#!/backoffice/subscription))
* Select your default Shipping Method for the "Instant Buy" functionality (It might require to create a new shipping method if there are no methods)
* Save Config

## Questions?

Please contact support@payapi.io for any questions.
