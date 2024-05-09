#!/usr/bin/env bash

set -e
. ./tests/e2e/bin/common.sh

step "Starting E2E docker containers"
CWD="$CWD" redirect_output docker-compose -f "$E2E_ROOT/env/docker-compose.yml" up -d
