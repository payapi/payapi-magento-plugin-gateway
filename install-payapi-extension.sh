#!/bin/bash
#
echo "PAYAPI EXTENSION FOR MAGENTO 2"

if [ $# != 2 ]; then
	echo "WRONG PARAMETERS. Please run sh install-payapi-extension <magento_user@remote_server> <magento_home_directory>"
	echo "Failed"
else
# 1st get 
ssh -oStrictHostKeyChecking=no $1 << HERE
# create release folder
cd $2
mkdir -p app/code
rm -rf app/code/Payapi
#Download file and unzip
echo "Downloading..."
wget 'https://storage.googleapis.com/public0/payapi-magento-plugin/latest/payapi-magento-plugin.zip' -O app/code/payapi-magento-plugin.zip
unzip -o app/code/payapi-magento-plugin.zip -d app/code 

#remove zip
rm -f app/code/payapi-magento-plugin.zip

#Install
echo "Installing..."
php bin/magento setup:upgrade
php bin/magento setup:di:compile
rm -rf var/di var/generation var/cache/* var/page_cache/*
echo "Done"

HERE
fi
