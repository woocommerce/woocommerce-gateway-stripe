#!/usr/bin/env bash

set -e

echo "Running PHPUnit tests via wp-env..."

# Install composer dependencies inside the tests-cli container
wp-env run tests-cli --env-cwd=wp-content/plugins/woocommerce-gateway-stripe -- composer install --no-interaction --quiet 2>/dev/null || true

# Run PHPUnit
wp-env run tests-cli --env-cwd=wp-content/plugins/woocommerce-gateway-stripe -- \
	phpunit --configuration phpunit.xml.dist "$@"
