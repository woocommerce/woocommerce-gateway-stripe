#!/bin/bash
# bin/docker-worktree-status.sh
# Shows status of all worktrees and their Docker environments.
# Usage: npm run worktree:status

set -e

if [[ -t 1 ]]; then
    YELLOW='\033[1;33m'
    BOLD='\033[1m'
    NC='\033[0m'
else
    YELLOW=''
    BOLD=''
    NC=''
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
CURRENT_DIR="$(pwd)"

generate_worktree_id() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g'
}

worktrees=()
while IFS= read -r line; do
    if [[ $line =~ ^worktree\ (.+)$ ]]; then
        worktrees+=("${BASH_REMATCH[1]}")
    fi
done < <(git -C "$REPO_ROOT" worktree list --porcelain)

containers=$(docker ps -a --filter "name=wcstripe_wp_" --format '{{.Names}}' 2>/dev/null || true)

echo ""
echo -e "${BOLD}Worktree Status${NC}"
echo ""
printf "  ${BOLD}%-6s %-30s %-14s %s${NC}\n" "PORT" "URL" "STATUS" "NAME"

orphan_containers=()

for worktree_path in "${worktrees[@]}"; do
    worktree_name=$(basename "$worktree_path")
    port=""
    status="no container"
    url="n/a"
    worktree_id=""

    if [[ -f "$worktree_path/.env" ]]; then
        port=$(grep '^WORDPRESS_PORT=' "$worktree_path/.env" 2>/dev/null | cut -d= -f2)
        worktree_id=$(grep '^WORKTREE_ID=' "$worktree_path/.env" 2>/dev/null | cut -d= -f2)
    fi

    if [[ -z "$worktree_id" ]]; then
        if [[ "$worktree_path" == "$REPO_ROOT" ]]; then
            worktree_id="default"
            [[ -z "$port" ]] && port="8072"
        else
            worktree_id=$(generate_worktree_id "$worktree_name")
        fi
    fi

    container_name="wcstripe_wp_$worktree_id"

    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name}$"; then
        status="running"
    elif docker ps -a --format '{{.Names}}' 2>/dev/null | grep -q "^${container_name}$"; then
        status="stopped"
    fi

    [[ -n "$port" ]] && url="http://localhost:$port"

    display_name="$worktree_name"
    [[ "$worktree_path" == "$REPO_ROOT" ]] && display_name="$worktree_name (main)"
    [[ "$worktree_path" == "$CURRENT_DIR" ]] && display_name="* $display_name"

    printf "  %-6s %-30s %-14s %s\n" "${port:-n/a}" "$url" "$status" "$display_name"
done

for container_name in $containers; do
    [[ -z "$container_name" ]] && continue
    container_worktree_id="${container_name#wcstripe_wp_}"
    found=false

    for worktree_path in "${worktrees[@]}"; do
        worktree_name=$(basename "$worktree_path")
        if [[ -f "$worktree_path/.env" ]]; then
            worktree_id=$(grep '^WORKTREE_ID=' "$worktree_path/.env" 2>/dev/null | cut -d= -f2)
            [[ -z "$worktree_id" ]] && worktree_id=$(generate_worktree_id "$worktree_name")
        elif [[ "$worktree_path" == "$REPO_ROOT" ]]; then
            worktree_id="default"
        else
            worktree_id=$(generate_worktree_id "$worktree_name")
        fi
        [[ "$container_worktree_id" == "$worktree_id" ]] && found=true && break
    done

    [[ "$found" == "false" ]] && orphan_containers+=("$container_name")
done

if [[ ${#orphan_containers[@]} -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Warnings:${NC}"
    for orphan in "${orphan_containers[@]}"; do
        echo "  - Orphan container: $orphan (no matching worktree)"
    done
    echo ""
    echo "  To clean up: docker rm -f ${orphan_containers[*]}"
fi

echo ""
