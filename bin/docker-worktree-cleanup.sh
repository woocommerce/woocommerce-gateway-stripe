#!/bin/bash
# bin/docker-worktree-cleanup.sh
# Run this before `git worktree remove` to clean up Docker resources.

set -e

WORKTREE_ID="default"
if [[ -f ".env" ]]; then
    source .env
else
    echo "No .env file detected, which means you might not be on a worktree."
    read -p "Do you want to continue? (y/N) " -r
    if [[ ! ${REPLY,,} =~ ^y(es)?$ ]]; then
        exit 0
    fi
fi

echo "Stopping containers for this worktree..."
npm run down

TEST_DB_NAME="wcstripe_tests_${WORKTREE_ID}"
if docker ps --format '{{.Names}}' | grep -q "wcstripe_db"; then
    echo "Checking for test database: ${TEST_DB_NAME}"
    DB_EXISTS=$(docker exec wcstripe_db mysql -uroot -pwordpress -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${TEST_DB_NAME}';" 2>/dev/null | grep -c "${TEST_DB_NAME}" || true)
    if [[ "$DB_EXISTS" -gt 0 ]]; then
        echo "Dropping test database: ${TEST_DB_NAME}"
        docker exec wcstripe_db mysql -uroot -pwordpress -e "DROP DATABASE IF EXISTS \`${TEST_DB_NAME}\`;" 2>/dev/null
        echo "Test database dropped."
    else
        echo "No test database found for this worktree."
    fi
else
    echo "Database container not running, skipping test database cleanup."
fi

if [[ -f ".env" ]]; then
    echo "Removing .env"
    rm .env
fi

echo "Cleanup complete. You can now run: git worktree remove $(pwd)"
