#!/bin/bash
# bin/docker-worktree-cleanup.sh
# Run this before `git worktree remove` to clean up Docker resources.

set -e

# Refuse to run from the main checkout. The main checkout's .env exists after
# the first `up`, which would otherwise let this script silently stop the main
# WP container, drop wcstripe_tests_default, and delete the main .env.
GIT_DIR=$(git rev-parse --git-dir 2>/dev/null || true)
GIT_COMMON_DIR=$(git rev-parse --git-common-dir 2>/dev/null || true)
if [[ -n "$GIT_DIR" && "$GIT_DIR" == "$GIT_COMMON_DIR" ]]; then
    echo "ERROR: worktree:cleanup is destructive and you are in the main checkout:"
    echo "       $(pwd)"
    echo
    echo "It would stop the main WP container, drop wcstripe_tests_default, and"
    echo "delete .env. To tear down the main checkout's docker state explicitly:"
    echo
    echo "  npm run down"
    echo "  docker exec wcstripe_db mariadb -uroot -pwordpress -e \\"
    echo "    'DROP DATABASE IF EXISTS wcstripe_tests_default;'"
    echo "  rm .env"
    exit 1
fi

WORKTREE_ID="default"
if [[ -f ".env" ]]; then
    source .env
else
    echo "No .env file detected, which means you might not be on a worktree."
    read -p "Do you want to continue? (y/N) " -r
    if [[ ! "$REPLY" =~ ^[Yy] ]]; then
        exit 0
    fi
fi

echo "Stopping containers for this worktree..."
npm run down

TEST_DB_NAME="wcstripe_tests_${WORKTREE_ID}"
if docker ps --format '{{.Names}}' | grep -q "wcstripe_db"; then
    echo "Checking for test database: ${TEST_DB_NAME}"
    DB_EXISTS=$(docker exec wcstripe_db mariadb -uroot -pwordpress -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${TEST_DB_NAME}';" 2>/dev/null | grep -c "${TEST_DB_NAME}" || true)
    if [[ "$DB_EXISTS" -gt 0 ]]; then
        echo "Dropping test database: ${TEST_DB_NAME}"
        docker exec wcstripe_db mariadb -uroot -pwordpress -e "DROP DATABASE IF EXISTS \`${TEST_DB_NAME}\`;" 2>/dev/null
        echo "Test database dropped."
    else
        echo "No test database found for this worktree."
    fi
else
    echo "Database container not running, skipping test database cleanup."
fi

# Tear down this worktree's e2e Docker stack (wordpress + mysql + stripe-listener
# and its named volume). `docker compose -p <project> down` locates containers by
# their project label, so no compose file is needed here. A worktree .env that
# still holds the main-checkout default project means it was copied from the main
# checkout — tearing that project down would kill the main checkout's stack, so
# skip it (worktree:setup warns about such .env files).
. "$(dirname "${BASH_SOURCE[0]}")/../tests/e2e/bin/e2e-stack-defaults.sh"
if [[ -z "$E2E_PROJECT" ]]; then
    E2E_PROJECT="$E2E_DEFAULT_PROJECT-${WORKTREE_ID}"
fi
if [[ "$E2E_PROJECT" == "$E2E_DEFAULT_PROJECT" ]]; then
    echo "Skipping e2e stack teardown: E2E_PROJECT is the main-checkout default ($E2E_DEFAULT_PROJECT)."
elif [[ -n "$(docker compose -p "$E2E_PROJECT" ps -a -q 2>/dev/null)" ]]; then
    echo "Tearing down e2e Docker stack: ${E2E_PROJECT}"
    docker compose -p "$E2E_PROJECT" down --volumes --remove-orphans
else
    echo "No e2e Docker stack found for this worktree."
fi

if [[ -f ".env" ]]; then
    echo "Removing .env"
    rm .env
fi

echo "Cleanup complete. You can now run: git worktree remove $(pwd)"
