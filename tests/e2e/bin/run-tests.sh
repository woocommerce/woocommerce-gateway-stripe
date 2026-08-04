#!/usr/bin/env bash

set -e
. ./tests/e2e/bin/common.sh

if [[ -f "$E2E_ROOT/config/local.env" ]]; then
	. "$E2E_ROOT/config/local.env"
fi

# If --base_url argument is present use the remote server setup.
if [[ "$*" == *"--base_url"* ]]; then
	$E2E_ROOT/env/e2e.sh "$@"
	exit
fi

# If no --base_url received, run the tests against the E2E docker containers.

TEST_ENV="NODE_CONFIG_DIR='tests/e2e/test-data'"

TEST_ARGS=""
accepted_args=("--base_url" "--project")
for arg in "$@"; do
	key=$(echo $arg | cut -f1 -d=)
	value=$(echo $arg | cut -f2 -d=)

	if [[ ${accepted_args[*]} =~ "${key}" ]]; then
		v="${key/--/}"
		declare $v="${value}"
	else
		TEST_ARGS="$TEST_ARGS $arg"
	fi
done

if [[ "wordpress" != "$(docker compose -p wcstripe-e2e ps --services --filter "status=running" | grep wordpress)" ]]; then
	error "Docker E2E containers are not running, please start them with 'npm run test:e2e-up' or 'npm run test:e2e-setup' and try again."
	exit 1
fi

TEST_ENV="$TEST_ENV DOCKER=true E2E_ROOT=${E2E_ROOT} BASE_URL='http://localhost:8088'"
TEST_ENV="$TEST_ENV ADMIN_USER='admin' ADMIN_PASSWORD='admin'"

# The docker site can't receive real webhooks (no tunnel), so seed what the
# Adaptive Pricing availability checks read: webhook data, the cached status
# transient is_webhook_enabled() trusts without calling Stripe, and PMC.
if [[ "adaptive-pricing" == "$project" ]]; then
	echo "Seeding Adaptive Pricing prerequisites"
	cli wp option patch insert woocommerce_stripe_settings pmc_enabled 'yes'
	cli wp option patch insert woocommerce_stripe_settings test_webhook_data --format=json '{"id":"we_e2e_placeholder","secret":"whsec_e2e_placeholder"}'
	# Delete first: `wp transient set` exits non-zero when the value is unchanged, which under
	# `set -e` would abort seeding on any re-run inside the TTL. Deleting a missing transient is a
	# no-op, so this stays correct on a fresh site and refreshes the TTL on a repeat run.
	cli wp transient delete wcstripe_webhook_status_test
	cli wp transient set wcstripe_webhook_status_test enabled 7200
	# Cookie-gated mu-plugin that simulates the shopper's country for
	# conversion tests (Stripe's "+location_XX" customer_email test hook).
	cli sh -c "mkdir -p /var/www/html/wp-content/mu-plugins && cp /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/env/mu-plugins/wc-stripe-e2e-location-simulation.php /var/www/html/wp-content/mu-plugins/"
fi

cross-env $TEST_ENV playwright test --config=tests/e2e/config/playwright.config.js $TEST_ARGS ${project:+--project=$project}
