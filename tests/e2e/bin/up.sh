#!/usr/bin/env bash
set -e

. ./tests/e2e/bin/common.sh

step "Starting E2E docker containers"
docker-compose -f "$E2E_ROOT/env/docker-compose.yml" up -d
