#!/bin/bash
# bin/docker-preflight.sh
# Checks prerequisites before starting WordPress containers.
# Auto-starts shared infrastructure from the main checkout if needed.

set -e

infra_needed=false

if ! docker network inspect wcstripe-network > /dev/null 2>&1; then
    infra_needed=true
fi

if [[ "$infra_needed" == "false" ]]; then
    for volume in wcstripe-plugins wcstripe-themes wcstripe-uploads wcstripe-mu-plugins; do
        if ! docker volume inspect "$volume" > /dev/null 2>&1; then
            infra_needed=true
            break
        fi
    done
fi

if [[ "$infra_needed" == "false" ]]; then
    if ! docker inspect -f '{{.State.Running}}' wcstripe_db 2>/dev/null | grep -q true; then
        infra_needed=true
    fi
fi

if [[ "$infra_needed" == "true" ]]; then
    echo "Shared infrastructure not running. Starting it now..."
    echo ""

    GIT_DIR=$(git rev-parse --git-dir 2>/dev/null)
    GIT_COMMON_DIR=$(git rev-parse --git-common-dir 2>/dev/null)

    if [[ "$GIT_DIR" != "$GIT_COMMON_DIR" ]]; then
        MAIN_CHECKOUT=$(dirname "$GIT_COMMON_DIR")
        echo "Running from worktree. Starting infrastructure from main checkout:"
        echo "  $MAIN_CHECKOUT"
        echo ""
        (cd "$MAIN_CHECKOUT" && npm run infra:up --silent)
    else
        SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
        REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
        (cd "$REPO_ROOT" && npm run infra:up --silent)
    fi

    echo ""
fi

# Ensure per-worktree log directories exist.
mkdir -p docker/logs/apache2
mkdir -p docker/logs/php
