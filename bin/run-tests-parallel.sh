#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-gateway-stripe/bin/install-wp-tests.sh

echo "Running the tests in parallel..."

# Use XDEBUG_MODE_PHPUNIT if set, otherwise disable for parallel run.
XDEBUG_MODE=${XDEBUG_MODE_PHPUNIT:-off}

docker compose exec -u www-data -e XDEBUG_MODE=$XDEBUG_MODE wordpress \
	/var/www/html/wp-content/plugins/woocommerce-gateway-stripe/vendor/bin/paratest \
	--configuration /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/phpunit.xml.dist \
	$*
