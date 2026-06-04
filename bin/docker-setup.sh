#!/bin/bash

# Exit if any command fails.
set -e

# Load worktree-specific config if available.
if [ -f ".env" ]; then
    source .env
fi

# Determine container name (from .env or parameter or default).
if [ -n "$WORKTREE_ID" ]; then
    DEFAULT_CONTAINER="wcstripe_wp_${WORKTREE_ID}"
else
    DEFAULT_CONTAINER="wcstripe_wp_default"
fi
WP_CONTAINER=${1:-$DEFAULT_CONTAINER}

# Determine site URL (from .env or environment or default).
DEFAULT_PORT=${WORDPRESS_PORT:-8072}
SITE_URL=${WP_URL:-"localhost:${DEFAULT_PORT}"}

redirect_output() {
	if [[ -z "$DEBUG" ]]; then
        "$@" > /dev/null
    else
        "$@"
    fi
}

# --user xfs forces the wordpress:cli container to use a user with the same ID
# as the main wordpress container. See:
# https://hub.docker.com/_/wordpress#running-as-an-arbitrary-user
cli()
{
	redirect_output docker run -i --env-file default.env --rm --volumes-from $WP_CONTAINER --network container:$WP_CONTAINER wordpress:cli "$@"
}

set +e
# Wait for the WordPress container's db to be reachable before configuring.
cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	echo "Waiting until the service is ready..."
	sleep 5s
	cli wp db check --skip_ssl --path=/var/www/html --quiet > /dev/null
done
set -e

# Refresh WP core files to align with whatever version `wp core update` last pulled.
echo "Refreshing WordPress core files..."
cli wp core download --force --skip-content --path=/var/www/html --quiet 2>/dev/null || true

# wp-config.php settings are per-worktree (file-based) — always set them
# (each worktree's container has its own wp-config.php under ./docker/wordpress).
echo "Configuring wp-config.php for this worktree..."
cli wp config set DOCKER_HOST "\$_SERVER['HTTP_X_FORWARDED_HOST'] ?? \$_SERVER['HTTP_X_ORIGINAL_HOST'] ?? \$_SERVER['HTTP_HOST'] ?? 'localhost'" --raw
docker exec $WP_CONTAINER bash -c 'WP_CONFIG_CONTENTS=$(cat /var/www/html/wp-config.php) || exit 1; echo "$WP_CONFIG_CONTENTS" | grep -q "\$_SERVER\[.HTTP_HOST.\] = DOCKER_HOST" || echo "$WP_CONFIG_CONTENTS" | sed "/define.*'\''DOCKER_HOST'\''/a \\\$_SERVER['\''HTTP_HOST'\''] = DOCKER_HOST;" > /var/www/html/wp-config.php'
cli wp config set DOCKER_REQUEST_URL "( ! empty( \$_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . DOCKER_HOST" --raw
cli wp config set WP_SITEURL DOCKER_REQUEST_URL --raw
cli wp config set WP_HOME DOCKER_REQUEST_URL --raw

echo "Enabling WordPress debug flags"
cli wp config set WP_DEBUG true --raw
cli wp config set WP_DEBUG_DISPLAY false --raw
cli wp config set WP_DEBUG_LOG true --raw
cli wp config set SCRIPT_DEBUG true --raw

echo "Enabling WordPress development environment (enforces Stripe testing mode)"
cli wp config set WP_ENVIRONMENT_TYPE development

# If the plugin is already active, we're done.
set +e
cli wp plugin is-active woocommerce-gateway-stripe > /dev/null
if [[ $? -eq 0 ]]; then
	set -e
	echo
	echo "WC Stripe Payment Gateway is installed and active"
	echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
	echo "You can login by using the username and password both as 'admin'"
	exit 0
fi
set -e

echo
echo "Setting up environment..."
echo

echo "Pulling the WordPress CLI docker image..."
docker pull wordpress:cli > /dev/null

# Only run core install when the shared DB doesn't already have WordPress installed.
set +e
cli wp core is-installed --path=/var/www/html 2>/dev/null
WP_INSTALLED=$?
set -e

if [[ $WP_INSTALLED -ne 0 ]]; then
	echo "Setting up WordPress..."
	cli wp core install \
		--path=/var/www/html \
		--url=$SITE_URL \
		--title=${SITE_TITLE-"WooCommerce Stripe Dev"} \
		--admin_name=${WP_ADMIN-admin} \
		--admin_password=${WP_ADMIN_PASSWORD-admin} \
		--admin_email=${WP_ADMIN_EMAIL-admin@example.com} \
		--skip-email

	echo "Updating WordPress to the latest version..."
	cli wp core update --quiet

	echo "Updating the WordPress database..."
	cli wp core update-db --quiet
else
	echo "WordPress already installed (shared DB), skipping core setup..."
fi

echo "Updating permalink structure"
cli wp rewrite structure '/%postname%/'

# Install/activate WooCommerce only if it's not already present in the shared plugins volume.
set +e
cli wp plugin is-installed woocommerce > /dev/null
WC_INSTALLED=$?
set -e
if [[ $WC_INSTALLED -ne 0 ]]; then
	echo "Installing and activating WooCommerce..."
	cli wp plugin install woocommerce --activate
else
	cli wp plugin activate woocommerce 2>/dev/null || true
fi

# Install/activate Storefront theme only if missing.
set +e
cli wp theme is-installed storefront > /dev/null
SF_INSTALLED=$?
set -e
if [[ $SF_INSTALLED -ne 0 ]]; then
	echo "Installing and activating Storefront theme..."
	cli wp theme install storefront --activate
else
	cli wp theme activate storefront 2>/dev/null || true
fi

echo "Adding basic WooCommerce settings..."
cli wp option set woocommerce_store_address "60 29th Street"
cli wp option set woocommerce_store_address_2 "#343"
cli wp option set woocommerce_store_city "San Francisco"
cli wp option set woocommerce_default_country "US:CA"
cli wp option set woocommerce_store_postcode "94110"
cli wp option set woocommerce_currency "USD"
cli wp option set woocommerce_product_type "both"
cli wp option set woocommerce_allow_tracking "no"
cli wp option set woocommerce_coming_soon "no"

echo "Importing WooCommerce shop pages..."
cli wp --user=admin wc tool run install_pages

set +e
cli wp plugin is-installed wordpress-importer > /dev/null
WPI_INSTALLED=$?
set -e
if [[ $WPI_INSTALLED -ne 0 ]]; then
	echo "Installing and activating the WordPress Importer plugin..."
	cli wp plugin install wordpress-importer --activate
else
	cli wp plugin activate wordpress-importer 2>/dev/null || true
fi

# Only import sample data once per shared install.
set +e
PRODUCT_COUNT=$(cli wp post list --post_type=product --format=count 2>/dev/null)
set -e
if [[ "${PRODUCT_COUNT:-0}" -lt 1 ]]; then
	echo "Importing some sample data..."
	cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip
fi

echo "Activating the WooCommerce Stripe Payment Gateway plugin..."
cli wp plugin activate woocommerce-gateway-stripe

# Install dev tools plugin if not present in the shared plugins volume.
set +e
cli wp plugin path woocommerce-gateway-stripe-dev-tools > /dev/null 2>&1
DEV_TOOLS_PRESENT=$?
set -e
if [[ $DEV_TOOLS_PRESENT -ne 0 ]]; then
	echo "Cloning dev tools plugin into shared plugins volume..."
	set +e
	git clone git@github.com:woocommerce/woocommerce-gateway-stripe-dev-tools.git docker/wordpress/wp-content/plugins/woocommerce-gateway-stripe-dev-tools
	CLONE_STATUS=$?
	set -e
	if [[ $CLONE_STATUS -ne 0 ]]; then
		echo
		echo "WARN: Could not clone the dev tools repository. Skipping the install."
	fi
fi
cli wp plugin activate woocommerce-gateway-stripe-dev-tools 2>/dev/null || true

echo
echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
echo "You can login by using the username and password both as 'admin'"
