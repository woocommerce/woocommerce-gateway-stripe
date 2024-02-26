#!/usr/bin/env bash
set -e

. ./tests/e2e/bin/common.sh

step "Stopping E2E docker containers"
docker-compose -p wcstripe-e2e down
