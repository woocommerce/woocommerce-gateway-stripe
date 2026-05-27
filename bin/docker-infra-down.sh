#!/bin/bash
# bin/docker-infra-down.sh
# Stops shared infrastructure (database, phpMyAdmin).
# Does NOT remove the named volumes so per-worktree WordPress containers can
# be restarted later without rebuilding wp-content. The wcstripe-network is
# torn down by `docker compose down` and recreated by the next `infra:up`.

set -e

# Load default env so docker compose can interpolate ${WCSTRIPE_SHARED_WP_PATH}
# and ${WCSTRIPE_DB_DATA_PATH} when stopping infra from any shell. `set -a`
# exports each sourced variable so docker compose (a child process) sees them.
set -a
source default.env
[[ -f ".env" ]] && source .env
set +a

echo "Stopping shared infrastructure..."
docker compose -f docker-compose.infra.yml down

echo ""
echo "Shared infrastructure stopped. Shared volumes are preserved; the"
echo "wcstripe-network was removed and will be recreated by the next infra:up."
echo "To also remove the shared volumes:"
echo "  docker volume rm wcstripe-plugins wcstripe-themes wcstripe-uploads wcstripe-mu-plugins"
