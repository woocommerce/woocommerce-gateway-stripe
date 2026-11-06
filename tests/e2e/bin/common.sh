#!/usr/bin/env bash

CWD=$(pwd)
E2E_ROOT="$CWD/tests/e2e"

# The compose project and host ports are machine-global, so worktree:setup
# records an isolated stack per worktree in .env. Resolution: env var > .env
# key > historical default (two lines per var: one ${:-} holds one fallback).
# Grep rather than source .env so unrelated keys can't leak into the scripts.
env_file_value() {
	grep "^$1=" "$CWD/.env" 2>/dev/null | tail -1 | cut -d= -f2-
}
. "$(dirname "${BASH_SOURCE[0]}")/e2e-stack-defaults.sh"
# Remember whether the caller targeted a project explicitly, before the
# fallbacks below erase that distinction; the guard after error() is defined
# lets an explicit choice through but stops an accidental default.
E2E_PROJECT_WAS_EXPLICIT=${E2E_PROJECT:+yes}
# Exported so docker compose can interpolate them in env/docker-compose.yml.
export E2E_PROJECT=${E2E_PROJECT:-$(env_file_value E2E_PROJECT)}
export E2E_PROJECT=${E2E_PROJECT:-$E2E_DEFAULT_PROJECT}
export E2E_WP_PORT=${E2E_WP_PORT:-$(env_file_value E2E_WP_PORT)}
export E2E_WP_PORT=${E2E_WP_PORT:-$E2E_DEFAULT_WP_PORT}
export E2E_DB_PORT=${E2E_DB_PORT:-$(env_file_value E2E_DB_PORT)}
export E2E_DB_PORT=${E2E_DB_PORT:-$E2E_DEFAULT_DB_PORT}

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

# A worktree that never ran worktree:setup resolves to the shared default
# project, and every e2e command would then operate on the main checkout's
# stack — the exact cross-checkout interference this isolation exists to
# prevent. Fail fast unless the caller explicitly asked for that project.
# In the main checkout (git-dir == git-common-dir) and outside git the
# defaults are the correct stack, so those pass.
if [[ -z "$E2E_PROJECT_WAS_EXPLICIT" && "$E2E_PROJECT" == "$E2E_DEFAULT_PROJECT" ]] \
	&& [[ "$(git rev-parse --git-dir 2>/dev/null)" != "$(git rev-parse --git-common-dir 2>/dev/null)" ]]; then
	error "This worktree has no isolated e2e stack configured, so this command would target the shared default '$E2E_DEFAULT_PROJECT' stack (the main checkout's)."
	echo "  Run 'npm run worktree:setup' to assign this worktree its own stack,"
	echo "  or export E2E_PROJECT explicitly to target a specific stack."
	exit 1
fi

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
