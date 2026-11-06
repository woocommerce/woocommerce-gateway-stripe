#!/usr/bin/env bash

set -e
. ./tests/e2e/bin/common.sh

load_e2e_local_env

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

# A reused local Docker store must not keep another project's availability stub.
cli sh -c "rm -f /var/www/html/wp-content/mu-plugins/wc-stripe-e2e-webhooks.php"

# Cookie-gated checkout-fields mu-plugin, used by adaptive-pricing and
# express-checkout specs. Installed for every project: it is inert unless a
# spec opts in with its cookies.
cli sh -c "mkdir -p /var/www/html/wp-content/mu-plugins && cp /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/env/mu-plugins/wc-stripe-e2e-checkout-fields.php /var/www/html/wp-content/mu-plugins/"

# Cookie-gated non-Stripe gateway for the add-payment-method and
# change-payment-method specs; inert unless a spec opts in with its cookie.
cli sh -c "cp /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/env/mu-plugins/wc-stripe-e2e-mock-gateway.php /var/www/html/wp-content/mu-plugins/"

# Saved-card consent tests opt in per browser instead of toggling Link on
# the Stripe account shared by parallel tests and CI jobs.
cli sh -c "cp /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/env/mu-plugins/wc-stripe-e2e-card-only.php /var/www/html/wp-content/mu-plugins/"

# The CLI listener forwards events without a registered localhost endpoint.
# Adaptive Pricing tests declare endpoint availability in a Docker-only fixture.
if [[ "adaptive-pricing" == "$project" ]]; then
	echo "Seeding Adaptive Pricing prerequisites"
	cli wp option patch insert woocommerce_stripe_settings pmc_enabled 'yes'
	cli wp option patch insert woocommerce_stripe_settings test_webhook_data --format=json '{"id":"we_e2e_placeholder","secret":"whsec_e2e_placeholder"}'
	cli sh -c "cp /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/env/mu-plugins/wc-stripe-e2e-webhooks.php /var/www/html/wp-content/mu-plugins/"
	trap 'cli sh -c "rm -f /var/www/html/wp-content/mu-plugins/wc-stripe-e2e-webhooks.php"' EXIT
	# Cookie-gated mu-plugin that simulates the shopper's country for
	# conversion tests (Stripe's "+location_XX" customer_email test hook).
	cli sh -c "mkdir -p /var/www/html/wp-content/mu-plugins && cp /var/www/html/wp-content/plugins/woocommerce-gateway-stripe/tests/e2e/env/mu-plugins/wc-stripe-e2e-location-simulation.php /var/www/html/wp-content/mu-plugins/"
fi

cross-env $TEST_ENV playwright test --config=tests/e2e/config/playwright.config.js $TEST_ARGS ${project:+--project=$project}
