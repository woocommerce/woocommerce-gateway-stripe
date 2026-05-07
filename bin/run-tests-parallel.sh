#!/usr/bin/env bash

set -e

echo "Installing the test environment..."

docker compose exec -u www-data -e PARATEST=true wordpress \
	/var/www/html/wp-content/plugins/woocommerce-gateway-stripe/bin/install-wp-tests.sh

echo "Running the tests in parallel..."

PLUGIN_DIR=/var/www/html/wp-content/plugins/woocommerce-gateway-stripe

# Use XDEBUG_MODE_PHPUNIT if set, otherwise disable for parallel run.
XDEBUG_MODE=${XDEBUG_MODE_PHPUNIT:-off}

# Paratest does not read the <report> section from phpunit.xml.dist to generate
# merged coverage output — it needs explicit --coverage-* CLI flags.  Xdebug is
# loaded in the container even when XDEBUG_MODE=off, so
# sebastian/environment's canCollectCodeCoverage() returns true, but PHPUnit
# workers crash when they try to start coverage with a driver that isn't in
# coverage mode.  Pass --no-coverage unless the caller has explicitly requested
# coverage via XDEBUG_MODE_PHPUNIT=coverage.
COVERAGE_FLAGS="--no-coverage"
PASSTHRU_PHP=()
if [ "$XDEBUG_MODE" = "coverage" ]; then
	COVERAGE_FLAGS="--coverage-cobertura ${PLUGIN_DIR}/php-coverage.xml --coverage-html ${PLUGIN_DIR}/phpunit-html"
	# Paratest spawns child PHP processes that don't inherit the XDEBUG_MODE
	# env var from Docker. Use --passthru-php to set it for each worker.
	PASSTHRU_PHP=(--passthru-php="-d xdebug.mode=coverage")
fi

docker compose exec -u www-data -e XDEBUG_MODE=$XDEBUG_MODE wordpress \
	${PLUGIN_DIR}/vendor/bin/paratest \
	--configuration ${PLUGIN_DIR}/phpunit-parallel.xml.dist \
	$COVERAGE_FLAGS \
	"${PASSTHRU_PHP[@]}" \
	"$@"
