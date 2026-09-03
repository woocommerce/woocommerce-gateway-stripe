#!/usr/bin/env bash

CWD=$(pwd)
E2E_ROOT="$CWD/tests/e2e"

# The compose project (and its container-name prefix) and the host ports are
# global to the machine: two checkouts using the defaults clobber each other's
# stacks. Override these to run an isolated second stack side-by-side.
# Exported so docker compose can interpolate them in env/docker-compose.yml.
export E2E_PROJECT=${E2E_PROJECT:-wcstripe-e2e}
export E2E_WP_PORT=${E2E_WP_PORT:-8088}
export E2E_DB_PORT=${E2E_DB_PORT:-6789}

ADMIN_USER=${ADMIN_USER-admin}
ADMIN_PASSWORD=${ADMIN_PASSWORD-admin}
ADMIN_EMAIL=${ADMIN_EMAIL-admin@example.com}

# --

error() {
	echo
	echo -e "\033[0;31mERROR\033[0m  $1"
}

step() {
	echo
	echo -e "\033[0;34m=>\033[0m $1"
}

check_dep() {
	if ! $1 --version > /dev/null 2>&1; then
		echo
		error "This script needs \033[0;34m$1\033[0m, please install it and try again."
		exit 1
	fi
}

load_e2e_local_env() {
	if [[ -f "$E2E_ROOT/config/local.env" ]]; then
		# Unreplaced <placeholder> values from local.env.example are not valid shell:
		# sourcing one aborts the rest of the file, silently dropping every variable
		# below it. Blank them out so only the values actually filled in take effect.
		eval "$(sed -E 's/=<[^>]*>[[:space:]]*$/=/' "$E2E_ROOT/config/local.env")"
	fi
}

redirect_output() {
	if [[ "$DEBUG" = true ]]; then
        "$@"
    else
        "$@" >> ${E2E_ROOT}/e2e-setup.log 2>&1
    fi
}

validate_stripe_listener_credentials() {
	if [[ -z "$STRIPE_SECRET_KEY" ]]; then
		error "STRIPE_SECRET_KEY must be set in tests/e2e/config/local.env."
		return 1
	fi

	step "Validating Stripe listener credentials"
	# `--print-secret` returns a webhook signing secret on stdout, so only stderr
	# can be persisted safely.
	if ! docker run --rm stripe/stripe-cli listen --api-key="$STRIPE_SECRET_KEY" --print-secret > /dev/null 2>> "$E2E_ROOT/e2e-setup.log"; then
		error "Stripe listener credential validation failed."
		echo "  See tests/e2e/e2e-setup.log for details. OAuth keys are not supported by Stripe CLI listeners."
		return 1
	fi
}

# --user xfs forces the wordpress:cli container to use a user with the same ID as the main wordpress container.
# See: https://hub.docker.com/_/wordpress#running-as-an-arbitrary-user
cli() {
	docker run -i --rm --user 33:33 --env-file ${E2E_ROOT}/env/default.env --volumes-from "${E2E_PROJECT}-wordpress" --network container:"${E2E_PROJECT}-wordpress" wordpress:cli "$@"
}
