#!/usr/bin/env bash

set -e
. ./tests/e2e/bin/common.sh

if [[ -f "$E2E_ROOT/config/local.env" ]]; then
	# Unreplaced <placeholder> values from local.env.example are not valid shell:
	# sourcing one aborts the rest of the file, silently dropping every variable
	# below it. Blank them out so only the values actually filled in take effect.
	eval "$(sed -E 's/=<[^>]*>[[:space:]]*$/=/' "$E2E_ROOT/config/local.env")"
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

DEPS_DIR="$E2E_ROOT/deps"

cd "$CWD"

check_dep 'docker'
check_dep 'curl'
check_dep 'jq'
check_dep 'php'

missing_plugin_zip_error() {
	error "Cannot obtain $1."
	echo "  Set GITHUB_TOKEN in tests/e2e/config/local.env, run 'gh auth login',"
	echo "  or place the zip at tests/e2e/deps/$1"
}

# Downloads <repo>'s latest release asset to tests/e2e/deps/<zip-name>.
#
# Sources are tried in order: GITHUB_TOKEN, the gh CLI, then an existing zip in
# tests/e2e/deps. A source that cannot deliver the zip says so and falls through
# to the next one, so a token inherited from the shell or a gh login without
# access to the private repos does not block a source that works.
fetch_plugin_zip() {
	local repo=$1
	local zip=$2
	local dest="$DEPS_DIR/$zip"
	# Downloads land on a temp path so a failed fetch cannot truncate the cached
	# zip we may still need to fall back to.
	local tmp="$dest.part"

	mkdir -p "$DEPS_DIR"

	if [[ -n "$GITHUB_TOKEN" ]]; then
		echo " - Fetching $repo latest version with GITHUB_TOKEN"

		local asset_id
		asset_id=$(curl -sfH "Authorization: token $GITHUB_TOKEN" "https://api.github.com/repos/$repo/releases/latest" | jq -r '.assets[0].id // empty')

		if [[ -n "$asset_id" ]] && redirect_output curl -sfL \
			-H "Authorization: token $GITHUB_TOKEN" \
			-H "Accept: application/octet-stream" \
			--output "$tmp" \
			"https://api.github.com/repos/$repo/releases/assets/$asset_id"; then
			mv "$tmp" "$dest"
			return
		fi

		rm -f "$tmp"
		echo " - GITHUB_TOKEN cannot download $zip from $repo, trying the next source"
	fi

	if command -v gh > /dev/null 2>&1; then
		echo " - Fetching $repo latest version with the GitHub CLI"

		if redirect_output gh release download --repo "$repo" --pattern '*.zip' --output "$tmp" --clobber; then
			mv "$tmp" "$dest"
			return
		fi

		rm -f "$tmp"
		echo " - gh cannot download $zip, trying the next source (see tests/e2e/e2e-setup.log)"
	fi

	if [[ -s "$dest" ]]; then
		echo " - Using tests/e2e/deps/$zip"
		return
	fi

	missing_plugin_zip_error "$zip"
	exit 1
}

if ! docker info > /dev/null 2>&1; then
	echo
	error "Docker is not running, please start it and try again."
	exit 1
fi

if [[ -z "$STRIPE_PUB_KEY" || -z "$STRIPE_SECRET_KEY" ]]; then
	echo
	error "STRIPE_PUB_KEY and STRIPE_SECRET_KEY must be set in tests/e2e/config/local.env."
	exit 1
fi

# Resolve both plugins before building the environment.
step "Fetching plugin dependencies"
fetch_plugin_zip "woocommerce/woocommerce-subscriptions" "woocommerce-subscriptions.zip"
fetch_plugin_zip "woocommerce/woocommerce-pre-orders" "woocommerce-pre-orders.zip"

step "Starting E2E docker containers"
if [ "$CI" = "true" ]; then
    CWD="$CWD" E2E_ROOT="$E2E_ROOT" redirect_output docker compose -p "$E2E_PROJECT" -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d
else
    CWD="$CWD" E2E_ROOT="$E2E_ROOT" redirect_output docker compose -p "$E2E_PROJECT" --env-file "$E2E_ROOT"/config/local.env -f "$E2E_ROOT"/env/docker-compose.yml up --build --force-recreate -d
fi

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
	--url="http://localhost:${E2E_WP_PORT}" \
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
	# If specified version is 'beta' or 'rc', fetch the latest matching version from WordPress.org API.
	# jq sorts keys lexically ("9.9.0-beta.1" > "11.0.0-beta.1"), so sort with PHP's
	# version_compare instead to get the actual latest version.
	if [[ $WC_VERSION == 'beta' || $WC_VERSION == 'rc' ]]; then
		REQUESTED_WC_VERSION=$WC_VERSION
		WC_VERSION=$(curl -s https://api.wordpress.org/plugins/info/1.0/woocommerce.json | \
			jq -r --arg type "$WC_VERSION" '.versions | keys[] | select(match($type;"i"))' | \
			php -r '$v = array_filter( array_map( "trim", file( "php://stdin" ) ) ); usort( $v, "version_compare" ); echo end( $v ) ?: "";')

		if [[ -z "$WC_VERSION" ]]; then
			error "Could not resolve the latest WooCommerce '${REQUESTED_WC_VERSION}' version from the WordPress.org API."
			exit 1
		fi
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

redirect_output cli wp --user=${ADMIN_USER} wc tool run install_pages

echo " - Configuring Shipping and Taxes"
redirect_output cli wp --user=${ADMIN_USER} wc shipping_zone create --name="Everywhere" --order=1
redirect_output cli wp --user=${ADMIN_USER} wc shipping_zone_method create 1 --method_id="flat_rate"
redirect_output cli wp --user=${ADMIN_USER} wc shipping_zone_method create 1 --method_id="free_shipping"
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
redirect_output cli wp option update woocommerce_stripe_settings --format=json "{\"enabled\":\"yes\",\"title\":\"Credit Card (Stripe)\",\"description\":\"Pay with your credit card via Stripe.\",\"api_credentials\":\"\",\"testmode\":\"yes\",\"test_publishable_key\":\"${STRIPE_PUB_KEY}\",\"test_secret_key\":\"${STRIPE_SECRET_KEY}\",\"publishable_key\":\"\",\"secret_key\":\"\",\"webhook\":\"\",\"test_webhook_secret\":\"\",\"webhook_secret\":\"\",\"inline_cc_form\":\"no\",\"statement_descriptor\":\"\",\"short_statement_descriptor\":\"\",\"capture\":\"yes\",\"payment_request\":\"yes\",\"payment_request_button_type\":\"buy\",\"payment_request_button_theme\":\"dark\",\"payment_request_button_locations\":[\"product\",\"cart\",\"checkout\"],\"payment_request_button_size\":\"default\",\"saved_cards\":\"yes\",\"logging\":\"no\",\"upe_checkout_experience_enabled\":\"yes\",\"test_connection_type\":\"connect\"}"

echo " - Enabling the ACH feature flag"
redirect_output cli wp option update _wcstripe_feature_lpm_ach 'yes'

echo " - Enabling the ACSS feature flag"
redirect_output cli wp option update _wcstripe_feature_lpm_acss 'yes'

echo " - Enabling the Optimized Checkout feature flag"
redirect_output cli wp option update _wcstripe_feature_oc 'yes'

step "Installing Woo Subscriptions"
echo " - Installing"
redirect_output cli wp plugin install /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/deps/woocommerce-subscriptions.zip --force

echo " - Activating"
redirect_output cli wp plugin activate woocommerce-subscriptions

step "Installing Woo Pre-Orders"
echo " - Installing"
redirect_output cli wp plugin install /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/deps/woocommerce-pre-orders.zip --force

echo " - Activating"
redirect_output cli wp plugin activate woocommerce-pre-orders

echo
echo "============================================================"
echo "WordPress     => $(cli wp core version)"
echo "WooCommerce   => $(cli wp plugin get woocommerce --field=version)"
echo "Stripe        => $(cli wp plugin get woocommerce-gateway-stripe --field=version)"
echo "Subscriptions => $(cli wp plugin get woocommerce-subscriptions --field=version)"
echo "Pre-Orders    => $(cli wp plugin get woocommerce-pre-orders --field=version)"
echo "============================================================"
echo
step "E2E environment up and running at http://localhost:${E2E_WP_PORT}/wp-admin/"
