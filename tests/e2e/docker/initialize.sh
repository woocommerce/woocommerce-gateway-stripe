#!/bin/bash

echo "Install WooCommerce & Storefront"
wp plugin install woocommerce --activate
wp theme install storefront --activate

echo "Creating WooCommerce Customer Account"
wp user create customer customer@woocommercecoree2etestsuite.com --user_pass=password --role=customer --path=/var/www/html

echo "Adding basic WooCommerce settings..."
wp option set woocommerce_store_address "Example Address Line 1"
wp option set woocommerce_store_address_2 "Example Address Line 2"
wp option set woocommerce_store_city "Example City"
wp option set woocommerce_default_country "US:CA"
wp option set woocommerce_store_postcode "94110"
wp option set woocommerce_currency "USD"
wp option set woocommerce_product_type "both"
wp option set woocommerce_allow_tracking "no"

echo "Importing WooCommerce shop pages..."
wp wc --user=admin tool run install_pages

echo "Installing and activating the WordPress Importer plugin..."
wp plugin install wordpress-importer --activate

echo "Importing WooCommerce sample products..."
wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

# echo "Activate <your-extension>"
# wp plugin activate your-extension

echo "Success! Your E2E Test Environment is now ready."
