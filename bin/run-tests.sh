#!/usr/bin/env bash

set -e

# Source .env if available for worktree-specific config (needed for WORKTREE_ID).
WORKTREE_ID="default"
if [ -f ".env" ]; then
    source .env
fi

TEST_DB_NAME="wcstripe_tests_${WORKTREE_ID}"

echo "Installing the test environment..."
echo "Using test database: ${TEST_DB_NAME}"

docker compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-gateway-stripe/bin/install-wp-tests.sh "${TEST_DB_NAME}"

echo "Running the tests..."

# Use XDEBUG_MODE_PHPUNIT if set, otherwise use "coverage" as default.
XDEBUG_MODE=${XDEBUG_MODE_PHPUNIT:-coverage}

docker compose exec -u www-data -e XDEBUG_MODE=$XDEBUG_MODE wordpress \
	/var/www/html/wp-content/plugins/woocommerce-gateway-stripe/vendor/bin/phpunit \
	--configuration /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/phpunit.xml.dist \
	"$@"
