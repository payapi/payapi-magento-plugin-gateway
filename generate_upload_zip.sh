#!/bin/bash
#
echo "PAYAPI - MAGENTO 2. GENERATE NEW RELEASE AND UPLOAD TO GCLOUD"

find . -name '*.DS_Store' -type f -delete
zip -r payapi-magento-plugin.zip Payapi/* i18n/*
gsutil cp payapi-magento-plugin.zip gs://public0/payapi-magento-plugin/latest
echo "Done"