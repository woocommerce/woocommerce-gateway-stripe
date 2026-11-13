#!/usr/bin/env bash
set -e

. ./tests/e2e/bin/common.sh

load_e2e_local_env

step "Stopping E2E docker containers"
CWD="$CWD" redirect_output docker compose -p "$E2E_PROJECT" -f "$E2E_ROOT/env/docker-compose.yml" down
