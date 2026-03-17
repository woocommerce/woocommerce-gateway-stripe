#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker compose exec -u www-data wordpress \
	/var/www/html/wp-content/plugins/woocommerce-gateway-stripe/bin/install-wp-tests.sh

echo "Running the tests in parallel..."

# Use XDEBUG_MODE_PHPUNIT if set, otherwise disable for parallel run.
XDEBUG_MODE=${XDEBUG_MODE_PHPUNIT:-off}

# Paratest reads the <coverage> section from phpunit.xml.dist and enables coverage
# collection automatically.  Xdebug is loaded in the container even when
# XDEBUG_MODE=off, so sebastian/environment's canCollectCodeCoverage() returns
# true, but PHPUnit workers crash when they try to start coverage with a driver
# that isn't in coverage mode.  Pass --no-coverage unless the caller has
# explicitly requested coverage via XDEBUG_MODE_PHPUNIT=coverage.
COVERAGE_FLAG="--no-coverage"
if [ "$XDEBUG_MODE" = "coverage" ]; then
	COVERAGE_FLAG=""
fi

docker compose exec -u www-data -e XDEBUG_MODE=$XDEBUG_MODE wordpress \
	/var/www/html/wp-content/plugins/woocommerce-gateway-stripe/vendor/bin/paratest \
	--configuration /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/phpunit.xml.dist \
	$COVERAGE_FLAG \
	$*
