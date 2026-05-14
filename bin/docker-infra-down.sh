#!/bin/bash
# bin/docker-infra-down.sh
# Stops shared infrastructure (database, phpMyAdmin).
# Does NOT remove the named volumes or the wcstripe-network so per-worktree
# WordPress containers can be restarted later without rebuilding everything.

set -e

echo "Stopping shared infrastructure..."
docker compose -f docker-compose.infra.yml down

echo ""
echo "Shared infrastructure stopped. Volumes and the wcstripe-network are preserved."
echo "To remove the network and shared volumes entirely:"
echo "  docker network rm wcstripe-network"
echo "  docker volume rm wcstripe-plugins wcstripe-themes wcstripe-uploads wcstripe-mu-plugins"
