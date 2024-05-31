#!/usr/bin/env bash
set -e

. ./tests/e2e/bin/common.sh

rm -rf $E2E_ROOT/e2e-setup.log

step "Removing E2E docker folder"
rm -rf $E2E_ROOT/env/docker

step "Removing result folders"
rm -rf $E2E_ROOT/test-results
