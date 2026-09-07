#!/usr/bin/env bash

set -e
. ./tests/e2e/bin/common.sh

load_e2e_local_env
validate_stripe_listener_credentials

step "Starting E2E docker containers"
CWD="$CWD" redirect_output docker compose -p wcstripe-e2e --env-file $E2E_ROOT/config/local.env -f "$E2E_ROOT/env/docker-compose.yml" up -d
