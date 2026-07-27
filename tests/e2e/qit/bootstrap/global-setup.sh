#!/usr/bin/env bash
#
# QIT globalSetup phase — runs inside the Docker container where WP-CLI is available.
#
# QIT has already installed and activated:
#   - WordPress
#   - WooCommerce
#   - woocommerce-gateway-stripe (the SUT)
#   - woocommerce-subscriptions and woocommerce-pre-orders (from requires.plugins)
#
# This script configures the store, imports sample data, sets up Stripe base settings,
# and creates the pages needed by tests. The Stripe webhook secret is set later by the
# Playwright globalSetup JS running on the host.

set -e

echo "=== QIT Global Setup: Configuring WooCommerce store ==="

# Helper plugins
echo " - Installing disable-emails plugin"
wp plugin install disable-emails --activate

echo " - Installing WordPress Importer"
wp plugin install wordpress-importer --activate

# Theme
echo " - Installing Storefront theme"
wp theme install storefront --activate

# Store settings
echo " - Configuring store settings"
wp option set woocommerce_store_address "60 29th Street"
wp option set woocommerce_store_address_2 "#343"
wp option set woocommerce_store_city "San Francisco"
wp option set woocommerce_default_country "US:CA"
wp option set woocommerce_store_postcode "94110"
wp option set woocommerce_currency "USD"
wp option set woocommerce_product_type "both"
wp option set woocommerce_allow_tracking "no"
wp option set woocommerce_coming_soon "no"

# Permalink structure
echo " - Updating permalink structure"
wp rewrite structure '/%postname%/'

# WooCommerce pages
echo " - Creating WooCommerce pages"
wp --user=admin wc tool run install_pages

# Sample products
echo " - Importing sample products"
WC_PATH=$(wp plugin path woocommerce --dir)
if [ -f "${WC_PATH}/sample-data/sample_products.xml" ]; then
	wp import "${WC_PATH}/sample-data/sample_products.xml" --authors=skip
else
	echo "   Warning: WooCommerce sample data not found at ${WC_PATH}/sample-data/sample_products.xml. Skipping import."
fi

# Shipping
echo " - Configuring shipping zones"
wp --user=admin wc shipping_zone create --name="Everywhere" --order=1
wp --user=admin wc shipping_zone_method create 1 --method_id="flat_rate"
wp --user=admin wc shipping_zone_method create 1 --method_id="free_shipping"
wp option update --format=json woocommerce_flat_rate_1_settings '{"title":"Flat rate","tax_status":"taxable","cost":"10"}'

# Shortcode pages for classic checkout tests
echo " - Creating Cart and Checkout shortcode pages"
if ! wp post list --post_type=page --field=post_name | grep -q 'cart-shortcode'; then
	wp post create --post_type=page --post_title='Cart Shortcode' --post_name='cart-shortcode' --post_status=publish --page_template='template-fullwidth.php' --post_content='<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->'
fi
if ! wp post list --post_type=page --field=post_name | grep -q 'checkout-shortcode'; then
	wp post create --post_type=page --post_title='Checkout Shortcode' --post_name='checkout-shortcode' --post_status=publish --page_template='template-fullwidth.php' --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
fi

# Stripe base settings (webhook secret is set later by the Playwright global-setup.js on the host)
echo " - Configuring Stripe plugin settings"
wp option update woocommerce_stripe_settings --format=json '{"enabled":"yes","title":"Credit Card (Stripe)","description":"Pay with your credit card via Stripe.","api_credentials":"","testmode":"yes","test_publishable_key":"'"${STRIPE_PUB_KEY}"'","test_secret_key":"'"${STRIPE_SECRET_KEY}"'","publishable_key":"","secret_key":"","webhook":"","test_webhook_secret":"","webhook_secret":"","inline_cc_form":"no","statement_descriptor":"","short_statement_descriptor":"","capture":"yes","payment_request":"yes","payment_request_button_type":"buy","payment_request_button_theme":"dark","payment_request_button_locations":["product","cart","checkout"],"payment_request_button_size":"default","saved_cards":"yes","logging":"no","upe_checkout_experience_enabled":"yes","test_connection_type":"connect"}'

# Feature flags
echo " - Enabling feature flags"
wp option update _wcstripe_feature_lpm_ach 'yes'
wp option update _wcstripe_feature_lpm_acss 'yes'
wp option update _wcstripe_feature_oc 'yes'

echo
echo "============================================================"
echo "WordPress     => $(wp core version)"
echo "WooCommerce   => $(wp plugin get woocommerce --field=version)"
echo "Stripe        => $(wp plugin get woocommerce-gateway-stripe --field=version)"
echo "Subscriptions => $(wp plugin get woocommerce-subscriptions --field=version 2>/dev/null || echo 'not installed')"
echo "Pre-Orders    => $(wp plugin get woocommerce-pre-orders --field=version 2>/dev/null || echo 'not installed')"
echo "============================================================"
echo
echo "=== QIT Global Setup: Complete ==="
