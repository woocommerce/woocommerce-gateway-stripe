#!/usr/bin/env bash
set -e

PLUGIN_SLUG="woocommerce-gateway-stripe"
WP_DIR="/var/www/html"
PLUGIN_DIR="${WP_DIR}/wp-content/plugins/${PLUGIN_SLUG}"

cd "$PLUGIN_DIR"

# Install Composer deps
echo "→ Installing Composer dependencies..."
composer install --no-interaction --prefer-dist

# Install npm deps
echo "→ Installing npm dependencies..."
npm install

# Setup WordPress
# Site URL is dynamic (set in wp-config.php), so we use a placeholder here,
# WordPress will rewrite it to the actual host:port on first request.
cd "$WP_DIR"


if ! wp core is-installed 2>/dev/null; then
  echo "→ Installing WordPress..."
  wp core install \
    --url="http://localhost" \
    --title="Stripe Dev" \
    --admin_user="admin" \
    --admin_password="admin" \
    --admin_email="admin@example.com" \
    --skip-email

  echo "→ Updating permalink structure"
  wp rewrite structure '/%postname%/'
fi

echo "→ Updating WordPress to the latest version..."
wp core update --quiet

echo "→ Updating the WordPress database..."
wp core update-db --quiet

# Setup WooCommerce
if ! wp plugin is-active woocommerce 2>/dev/null; then
  echo "→ Installing WooCommerce..."
  wp plugin install woocommerce --activate

  echo "→ Updating WooCommerce settings..."
  wp option set woocommerce_store_address "60 29th Street"
  wp option set woocommerce_store_address_2 "#343"
  wp option set woocommerce_store_city "San Francisco"
  wp option set woocommerce_default_country "US:CA"
  wp option set woocommerce_store_postcode "94110"
  wp option set woocommerce_currency "USD"
  wp option set woocommerce_product_type "both"
  wp option set woocommerce_allow_tracking "no"
  wp option set woocommerce_coming_soon "no"

  echo "→ Installing the Storefront theme..."
  wp theme install storefront --activate

  echo "→ Importing WooCommerce shop pages..."
  wp --user=admin wc tool run install_pages

  echo "→ Installing the WordPress Importer plugin..."
  wp plugin install wordpress-importer --activate

  echo "→ Importing some sample data..."
  wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip
fi


# Setup the plugin
if ! wp plugin is-active woocommerce-gateway-stripe 2>/dev/null; then
  echo "→ Activating ${PLUGIN_SLUG}..."
  wp plugin activate "${PLUGIN_SLUG}" || echo "  plugin activation failed"

  echo "→ Installing dev tools plugin..."
  if git clone git@github.com:woocommerce/woocommerce-gateway-stripe-dev-tools.git "${WP_DIR}/wp-content/plugins/woocommerce-gateway-stripe-dev-tools"; then
	  wp plugin activate woocommerce-gateway-stripe-dev-tools
  else
	  echo "WARN: Could not clone the dev tools repository. Skipping the install."
  fi
fi

cat <<EOF

✓ Devcontainer ready.

  WordPress: open the Ports panel in VS Code and click the globe icon for port 80
  Admin:     <url>/wp-admin   (user: admin / pass: admin)
  Plugin:    ${PLUGIN_DIR}

  Run 'claude' or 'codex' in the terminal to start an agent.

EOF
