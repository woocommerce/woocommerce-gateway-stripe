#!/bin/bash

# This is a shared setup script that runs before all plugins' tests are executed.
#
# This is an advanced setup, usually you don't have to worry about this file.
#
# Use it to:
# - Set WordPress settings to a known state
# - Create shared test data
# - Set up caches and transients
# - Prepare the environment for the tests

# echo "Running shared setup..."
# wp option update blogname "Test Blog" # Update a WordPress setting
# wp post create --post_title="Shared Test Post" # Create a shared test post

# Add your shared setup commands here
. ./common.sh

step "Update permalink structure"
output_if_error wp rewrite structure '/%postname%/'

step "Configure WooCommerce"
output_if_error wp option set woocommerce_store_address "60 29th Street"
output_if_error wp option set woocommerce_store_address_2 "#343"
output_if_error wp option set woocommerce_store_city "San Francisco"
output_if_error wp option set woocommerce_default_country "US:CA"
output_if_error wp option set woocommerce_store_postcode "94110"
output_if_error wp option set woocommerce_currency "USD"
output_if_error wp option set woocommerce_product_type "both"
output_if_error wp option set woocommerce_allow_tracking "no"
output_if_error wp option set woocommerce_coming_soon "no"
output_if_error wp wc --user=admin tool run install_pages

step "Configure Shipping and Taxes"
output_if_error wp wc shipping_zone create --name="Everywhere" --order=1 --user=admin
output_if_error wp wc shipping_zone_method create 1 --method_id="flat_rate" --user=admin
output_if_error wp wc shipping_zone_method create 1 --method_id="free_shipping" --user=admin
output_if_error wp option update --format=json woocommerce_flat_rate_1_settings '{"title":"Flat rate","tax_status":"taxable","cost":"10"}'

step "Create Cart and Checkout shortcode pages"
output_if_error wp post create --post_type=page --post_title='Cart Shortcode' --post_name='cart-shortcode' --post_status=publish --page_template='template-fullwidth.php' --post_content='<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->'
output_if_error wp post create --post_type=page --post_title='Checkout Shortcode' --post_name='checkout-shortcode' --post_status=publish --page_template='template-fullwidth.php' --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'

echo "Create test products"
output_if_error wp wc product create --name="Simple Product" --slug="simple-product" --sku="simple-product" --user=admin --regular_price=10
output_if_error wp wc product create --name="Beanie" --slug="beanie" --sku="woo-beanie" --user=admin --regular_price=20 --sale_price=18
