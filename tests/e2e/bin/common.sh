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

redirect_output() {
	if [[ "$DEBUG" = true ]]; then
        "$@"
    else
        "$@" >> ${E2E_ROOT}/e2e-setup.log 2>&1
    fi
}

# --user xfs forces the wordpress:cli container to use a user with the same ID as the main wordpress container.
# See: https://hub.docker.com/_/wordpress#running-as-an-arbitrary-user
cli() {
	docker run -i --rm --user 33:33 --env-file ${E2E_ROOT}/env/default.env --volumes-from "${E2E_PROJECT}-wordpress" --network container:"${E2E_PROJECT}-wordpress" wordpress:cli "$@"
}
