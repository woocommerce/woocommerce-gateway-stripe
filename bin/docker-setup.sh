#!/usr/bin/env sh

# Exit if any command fails.
set -e

# --user xfs forces the wordpress:cli container to use a user with the same ID as the main wordpress container. See:
# https://hub.docker.com/_/wordpress#running-as-an-arbitrary-user
cli()
{
	docker run -it --rm --user xfs --volumes-from woocommerce_stripe_wordpress --network container:woocommerce_stripe_wordpress wordpress:cli "$@" > /dev/null
}

echo
echo "Setting up environment..."
echo

echo "Pulling the WordPress CLI docker image..."
docker pull wordpress:cli > /dev/null

echo "Setting up WordPress..."
cli wp core install --path=/var/www/html --url=localhost:8082 --title="WooCommerce Stripe Dev" --admin_name=admin --admin_password=admin --admin_email=admin@example.com --skip-email

echo "Updating WordPress to the latest version..."
cli wp core update --quiet

echo "Updating the WordPress database..."
cli wp core update-db --quiet

echo "Configuring paths to work with ngrok...";
cli config set DOCKER_REQUEST_URL "( ! empty( \$_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . ( ! empty( \$_SERVER['HTTP_HOST'] ) ? \$_SERVER['HTTP_HOST'] : 'localhost' )" --raw
cli config set WP_SITEURL DOCKER_REQUEST_URL --raw
cli config set WP_HOME DOCKER_REQUEST_URL --raw

echo "Installing and activating WooCommerce..."
cli wp plugin install woocommerce --activate

echo "Installing and activating Storefront theme..."
cli wp theme install storefront --activate

echo "Adding basic WooCommerce settings..."
cli wp option set woocommerce_store_address "60 29th Street"
cli wp option set woocommerce_store_address_2 "#343"
cli wp option set woocommerce_store_city "San Francisco"
cli wp option set woocommerce_default_country "US:CA"
cli wp option set woocommerce_store_postcode "94110"
cli wp option set woocommerce_currency "USD"
cli wp option set woocommerce_product_type "both"
cli wp option set woocommerce_allow_tracking "no"

echo "Importing WooCommerce shop pages..."
cli wp wc --user=admin tool run install_pages

echo "Installing and activating the WordPress Importer plugin..."
cli wp plugin install wordpress-importer --activate

echo "Importing some sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

echo "Activating the WooCommerce Stripe Payment Gateway plugin..."
cli wp plugin activate woocommerce-gateway-stripe

echo
echo "SUCCESS! You should now be able to access http://localhost:8082/wp-admin/"
echo "You can login by using the username and password both as 'admin'"
