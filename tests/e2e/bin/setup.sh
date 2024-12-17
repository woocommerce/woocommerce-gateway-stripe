#!/usr/bin/env bash

set -e
. ./tests/e2e/bin/common.sh

if [[ -f "$E2E_ROOT/config/local.env" ]]; then
	. "$E2E_ROOT/config/local.env"
fi

# If --base_url argument is present use the remote server setup.
if [[ "$*" == *"--base_url"* ]]; then
	$E2E_ROOT/env/e2e.sh --with_woo_setup --with_stripe_setup "$@"
	exit
fi

# If no --base_url received, setup the docker test environment.

DEBUG=false

# Override custom user/password from local.env, if any.
ADMIN_USER=admin
ADMIN_PASSWORD=admin

cd "$CWD"

check_dep 'docker'
check_dep 'curl'
check_dep 'jq'

if ! docker info > /dev/null 2>&1; then
	echo
	error "Docker is not running, please start it and try again."
	exit 1
fi

step "Starting E2E docker containers"
CWD="$CWD" E2E_ROOT="$E2E_ROOT" redirect_output docker compose -p wcstripe-e2e -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d wordpress

step "Configuring WordPress"
# Wait for containers to be started up before setup.
# The db being accessible means that the db container started and the WP has been downloaded and the plugin linked
set +e
redirect_output cli wp db check --path=/var/www/html --quiet
while [[ $? -ne 0 ]]; do
	echo " - Waiting for containers..."
	sleep 5
	redirect_output cli wp db check --path=/var/www/html --quiet
done
set -e

redirect_output cli wp core install \
	--path=/var/www/html \
	--url="http://localhost:8088" \
	--title="WCStripe E2E test store" \
	--admin_name="${ADMIN_USER}" \
	--admin_password="${ADMIN_PASSWORD}" \
	--admin_email="${ADMIN_EMAIL}" \
	--skip-email

if [[ -n "$WP_VERSION" && "$WP_VERSION" != "latest" ]]; then
	echo " - Installing WordPress ${WP_VERSION}..."
	redirect_output cli wp core update --version="$WP_VERSION" --force --quiet
else
	echo " - Updating WordPress to the latest version"
	redirect_output cli wp core update --quiet
fi

echo " - Updating the database"
redirect_output cli wp core update-db --quiet

echo " - Disabling debug mode"
redirect_output cli wp config set WP_DEBUG false --raw

echo " - Updating permalink structure"
redirect_output cli wp rewrite structure '/%postname%/'

echo " - Installing WordPress Importer"
redirect_output cli wp plugin install wordpress-importer --activate

echo " - Disable emails to avoid spamming"
redirect_output cli wp plugin install disable-emails --activate

# Install WooCommerce
if [[ -n "$WC_VERSION" && $WC_VERSION != 'latest' ]]; then
	# If specified version is 'beta', fetch the latest beta version from WordPress.org API
	if [[ $WC_VERSION == 'beta' ]]; then
		WC_VERSION=$(curl https://api.wordpress.org/plugins/info/1.0/woocommerce.json | jq -r '.versions | with_entries(select(.key|match("beta";"i"))) | keys[-1]' --sort-keys)
	fi
	step "Installing WooCommerce ${WC_VERSION}"
	redirect_output cli wp plugin install woocommerce --version="$WC_VERSION" --activate
else
	step "Installing WooCommerce"
	redirect_output cli wp plugin install woocommerce --activate
fi

step "Configuring WooCommerce"
redirect_output cli wp option set woocommerce_store_address "60 29th Street"
redirect_output cli wp option set woocommerce_store_address_2 "#343"
redirect_output cli wp option set woocommerce_store_city "San Francisco"
redirect_output cli wp option set woocommerce_default_country "US:CA"
redirect_output cli wp option set woocommerce_store_postcode "94110"
redirect_output cli wp option set woocommerce_currency "USD"
redirect_output cli wp option set woocommerce_product_type "both"
redirect_output cli wp option set woocommerce_allow_tracking "no"
redirect_output cli wp option set woocommerce_coming_soon "no"

echo " - Installing Storefront theme"
redirect_output cli wp theme install storefront --activate

redirect_output cli wp wc --user=${ADMIN_USER} tool run install_pages

echo " - Configuring Shipping and Taxes"
redirect_output cli wp wc shipping_zone create --name="Everywhere" --order=1 --user=${ADMIN_USER}
redirect_output cli wp wc shipping_zone_method create 1 --method_id="flat_rate" --user=${ADMIN_USER}
redirect_output cli wp wc shipping_zone_method create 1 --method_id="free_shipping" --user=${ADMIN_USER}
redirect_output cli wp option update --format=json woocommerce_flat_rate_1_settings '{"title":"Flat rate","tax_status":"taxable","cost":"10"}'

echo " - Creating Cart and Checkout shortcode pages"
if ! cli wp post list --post_type=page --field=post_name | grep -q 'cart-shortcode'; then
	redirect_output cli wp post create --post_type=page --post_title='Cart Shortcode' --post_name='cart-shortcode' --post_status=publish --page_template='template-fullwidth.php' --post_content='<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->'
fi
if ! cli wp post list --post_type=page --field=post_name | grep -q 'checkout-shortcode'; then
	redirect_output cli wp post create --post_type=page --post_title='Checkout Shortcode' --post_name='checkout-shortcode' --post_status=publish --page_template='template-fullwidth.php' --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
fi

echo " - Importing sample products"
redirect_output cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

step "Configuring WooCommerce Gateway Stripe"
echo " - Activating plugin"
redirect_output cli wp plugin activate woocommerce-gateway-stripe

echo " - Updating WooCommerce Gateway Stripe settings"
redirect_output cli wp option set woocommerce_stripe_settings --format=json "{\"enabled\":\"yes\",\"title\":\"Credit Card (Stripe)\",\"description\":\"Pay with your credit card via Stripe.\",\"api_credentials\":\"\",\"testmode\":\"yes\",\"test_publishable_key\":\"${STRIPE_PUB_KEY}\",\"test_secret_key\":\"${STRIPE_SECRET_KEY}\",\"publishable_key\":\"\",\"secret_key\":\"\",\"webhook\":\"\",\"test_webhook_secret\":\"\",\"webhook_secret\":\"\",\"inline_cc_form\":\"no\",\"statement_descriptor\":\"\",\"short_statement_descriptor\":\"\",\"capture\":\"yes\",\"payment_request\":\"yes\",\"payment_request_button_type\":\"buy\",\"payment_request_button_theme\":\"dark\",\"payment_request_button_locations\":[\"product\",\"cart\",\"checkout\"],\"payment_request_button_size\":\"default\",\"saved_cards\":\"yes\",\"logging\":\"no\",\"upe_checkout_experience_enabled\":\"yes\"}"

step "Installing Woo Subscriptions"
echo " - Fetching latest version"
LATEST_RELEASE_ASSET_ID=$(curl -sH "Authorization: token $GITHUB_TOKEN" https://api.github.com/repos/woocommerce/woocommerce-subscriptions/releases/latest | jq -r '.assets[0].id')

redirect_output curl -sLJ \
	-H "Authorization: token $GITHUB_TOKEN" \
	-H "Accept: application/octet-stream" \
	--output $E2E_ROOT/woocommerce-subscriptions.zip \
	https://api.github.com/repos/woocommerce/woocommerce-subscriptions/releases/assets/"$LATEST_RELEASE_ASSET_ID"

redirect_output cli wp plugin install /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/woocommerce-subscriptions.zip --force
rm -rf $E2E_ROOT/woocommerce-subscriptions.zip

redirect_output cli wp plugin activate woocommerce-subscriptions

echo
echo "============================================================"
echo "WordPress     => $(cli wp core version)"
echo "WooCommerce   => $(cli wp plugin get woocommerce --field=version)"
echo "Stripe        => $(cli wp plugin get woocommerce-gateway-stripe --field=version)"
echo "Subscriptions => $(cli wp plugin get woocommerce-subscriptions --field=version)"
echo "============================================================"
echo
step "E2E environment up and running at http://localhost:8088/wp-admin/"
