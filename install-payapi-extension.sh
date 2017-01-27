#!/bin/bash
#
echo "PAYAPI EXTENSION FOR MAGENTO 2"

if [ $# != 2 ]; then
	echo "WRONG PARAMETERS. Please run sh install-payapi-extension <ssh remote server> <magento root directory>"
	echo "Failed"
else
# 1st get 
ssh -oStrictHostKeyChecking=no $1 << HERE
# create release folder
cd $2
sudo mkdir -p app/code
sudo rm -rf app/code/Payapi
sudo rm -rf app/code/i18n/payapi
#Download file and unzip
echo "Downloading..."
sudo gsutil cp 'gs://public0/payapi-magento-plugin/latest/payapi-magento-plugin.zip' app/code/payapi-extension.zip
sudo unzip -o app/code/payapi-extension.zip -d app/code 

#remove zip
sudo rm -f app/code/payapi-extension.zip

#Install
echo "Installing..."
sudo php bin/magento setup:upgrade
sudo php bin/magento setup:di:compile
sudo rm -rf var/di var/generation var/cache/* var/page_cache/*
echo "Done"

HERE
fi
