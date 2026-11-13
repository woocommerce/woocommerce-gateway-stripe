#!/usr/bin/env bash
# The historical machine-global e2e Docker stack: compose project name (also
# the container-name prefix) and host ports. Single source of truth, sourced
# by tests/e2e/bin/common.sh (final fallbacks, so CI and single-checkout usage
# keep the exact historical behavior) and by the bin/docker-worktree-* scripts
# (the main checkout's assigned stack, and the guard that refuses to treat a
# worktree as owning it).
E2E_DEFAULT_PROJECT="wcstripe-e2e"
E2E_DEFAULT_WP_PORT=8088
E2E_DEFAULT_DB_PORT=6789
