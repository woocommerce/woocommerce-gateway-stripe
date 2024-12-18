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
accepted_args=("--base_url")
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

if [[ *"wordpress" == "$(docker compose -p wcstripe-e2e ps --services --filter "status=running" | grep wordpress)" ]]; then
	error "Docker E2E containers are not running, please start them with 'npm run test:e2e-up' or 'npm run test:e2e-setup' and try again."
	exit 1
fi

set -e

. ./tests/e2e/bin/common.sh

cd "$CWD"

TEST_ENV="$TEST_ENV DOCKER=true E2E_ROOT=${E2E_ROOT} BASE_URL='http://localhost:8088'"
TEST_ENV="$TEST_ENV ADMIN_USER='admin' ADMIN_PASSWORD='admin'"

cross-env $TEST_ENV playwright test --config=tests/e2e/config/playwright.config.js $TEST_ARGS
