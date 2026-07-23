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

# Adaptive Pricing is only offered while the plugin believes webhooks work and
# the payment method configurations API is on. The docker site cannot receive
# real webhooks (no tunnel), so seed exactly what the availability checks read:
# webhook data plus the cached status transient — which is_webhook_enabled()
# trusts without calling Stripe — and the PMC flag.
if [[ "adaptive-pricing" == "$project" ]]; then
	echo "Seeding Adaptive Pricing prerequisites"
	cli wp option patch insert woocommerce_stripe_settings pmc_enabled 'yes'
	cli wp option patch insert woocommerce_stripe_settings test_webhook_data --format=json '{"id":"we_e2e_placeholder","secret":"whsec_e2e_placeholder"}'
	cli wp transient set wcstripe_webhook_status_test enabled 7200
fi

cross-env $TEST_ENV playwright test --config=tests/e2e/config/playwright.config.js $TEST_ARGS ${project:+--project=$project}
