#!/usr/bin/env bash
set -e

. ./tests/e2e/bin/common.sh

step "Stopping E2E docker containers"
CWD="$CWD" redirect_output docker compose -p "$E2E_PROJECT" down
