#!/bin/bash
#
echo "PAYAPI GATEWAY (core) FOR MAGENTO 2"

if [ $# != 2 ]; then
	echo "WRONG PARAMETERS. Please run sh install-payapi-extension <magento_user@remote_server> <magento_home_directory>"
	echo "Failed"
else
# 1st get 
ssh -oStrictHostKeyChecking=no $1 << HERE
# create release folder
cd $2

#Install via composer
echo "Installing..."
composer require payapi/magento-plugin-gateway

#Compiling
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy en_GB fi_FI es_ES
rm -rf var/di var/generation var/cache/* var/page_cache/*
echo "Done"

HERE
fi
