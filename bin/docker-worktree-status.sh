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

# `git worktree list` always puts the main working tree first. REPO_ROOT is
# wherever this script was invoked from, which may itself be a worktree, so it
# cannot stand in for the main checkout.
MAIN_CHECKOUT="${worktrees[0]}"

. "$SCRIPT_DIR/../tests/e2e/bin/e2e-stack-defaults.sh"

# Query docker once up front; the per-worktree loop greps these snapshots
# instead of paying two `docker ps` round-trips per worktree.
containers=$(docker ps -a --filter "name=wcstripe_wp_" --format '{{.Names}}' 2>/dev/null || true)
running_containers=$(docker ps --format '{{.Names}}' 2>/dev/null || true)
all_containers=$(docker ps -a --format '{{.Names}}' 2>/dev/null || true)
# E2E stacks with their exact compose project and the directory compose ran
# from, read from labels rather than parsed out of container names.
e2e_stack_labels=$(docker ps -a --filter "name=$E2E_DEFAULT_PROJECT" --format '{{.Label "com.docker.compose.project"}}	{{.Label "com.docker.compose.project.working_dir"}}' 2>/dev/null | sort -u || true)

# Last occurrence wins, matching how `source .env` and the e2e scripts'
# env_file_value resolve duplicate keys; grepping all occurrences would turn
# a duplicated key into a multiline value that corrupts every comparison.
env_last_value() {
    grep "^$2=" "$1/.env" 2>/dev/null | tail -1 | cut -d= -f2-
}

echo ""
echo -e "${BOLD}Worktree Status${NC}"
echo ""
printf "  ${BOLD}%-6s %-30s %-14s %-9s %-11s %s${NC}\n" "PORT" "URL" "STATUS" "E2E-PORT" "E2E-STATUS" "NAME"

orphan_containers=()
known_e2e_projects=()

for worktree_path in "${worktrees[@]}"; do
    worktree_name=$(basename "$worktree_path")
    port=""
    status="no container"
    url="n/a"
    worktree_id=""
    e2e_project=""
    e2e_port=""
    e2e_status="no stack"

    if [[ -f "$worktree_path/.env" ]]; then
        port=$(env_last_value "$worktree_path" WORDPRESS_PORT)
        worktree_id=$(env_last_value "$worktree_path" WORKTREE_ID)
        e2e_project=$(env_last_value "$worktree_path" E2E_PROJECT)
        e2e_port=$(env_last_value "$worktree_path" E2E_WP_PORT)
    fi

    if [[ -z "$worktree_id" ]]; then
        if [[ "$worktree_path" == "$MAIN_CHECKOUT" ]]; then
            worktree_id="default"
            [[ -z "$port" ]] && port="8072"
        else
            worktree_id=$(generate_worktree_id "$worktree_name")
        fi
    fi

    # The main checkout uses the historical e2e defaults even before
    # worktree:setup has written them to its .env.
    if [[ -z "$e2e_project" && "$worktree_path" == "$MAIN_CHECKOUT" ]]; then
        e2e_project="$E2E_DEFAULT_PROJECT"
        [[ -z "$e2e_port" ]] && e2e_port="$E2E_DEFAULT_WP_PORT"
    fi
    [[ -n "$e2e_project" ]] && known_e2e_projects+=("$e2e_project")

    container_name="wcstripe_wp_$worktree_id"

    if grep -q "^${container_name}$" <<< "$running_containers"; then
        status="running"
    elif grep -q "^${container_name}$" <<< "$all_containers"; then
        status="stopped"
    fi

    if [[ -n "$e2e_project" ]]; then
        if grep -q "^${e2e_project}-wordpress$" <<< "$running_containers"; then
            e2e_status="running"
        elif grep -q "^${e2e_project}-wordpress$" <<< "$all_containers"; then
            e2e_status="stopped"
        fi
    fi

    [[ -n "$port" ]] && url="http://localhost:$port"

    display_name="$worktree_name"
    [[ "$worktree_path" == "$MAIN_CHECKOUT" ]] && display_name="$worktree_name (main)"
    [[ "$worktree_path" == "$CURRENT_DIR" ]] && display_name="* $display_name"

    printf "  %-6s %-30s %-14s %-9s %-11s %s\n" "${port:-n/a}" "$url" "$status" "${e2e_port:-n/a}" "$e2e_status" "$display_name"
done

for container_name in $containers; do
    [[ -z "$container_name" ]] && continue
    container_worktree_id="${container_name#wcstripe_wp_}"
    found=false

    for worktree_path in "${worktrees[@]}"; do
        worktree_name=$(basename "$worktree_path")
        if [[ -f "$worktree_path/.env" ]]; then
            worktree_id=$(env_last_value "$worktree_path" WORKTREE_ID)
            [[ -z "$worktree_id" ]] && worktree_id=$(generate_worktree_id "$worktree_name")
        elif [[ "$worktree_path" == "$MAIN_CHECKOUT" ]]; then
            worktree_id="default"
        else
            worktree_id=$(generate_worktree_id "$worktree_name")
        fi
        [[ "$container_worktree_id" == "$worktree_id" ]] && found=true && break
    done

    [[ "$found" == "false" ]] && orphan_containers+=("$container_name")
done

# E2E stacks whose worktree is gone (or whose .env no longer references them)
# keep three containers plus a named volume alive; surface them for cleanup.
# Ownership comes from the compose working-dir label: a stack whose directory
# is not one of this repo's worktrees (a second clone, some other project)
# is not ours to flag, let alone to suggest tearing down.
orphan_e2e_projects=()
orphan_e2e_display=()
seen_e2e_projects=" "
while IFS=$'\t' read -r project workdir; do
    [[ -z "$project" ]] && continue
    [[ "$seen_e2e_projects" == *" $project "* ]] && continue
    seen_e2e_projects="$seen_e2e_projects$project "

    found=false
    for known in "${known_e2e_projects[@]}"; do
        [[ "$project" == "$known" ]] && found=true && break
    done
    [[ "$found" == "true" ]] && continue

    if [[ -n "$workdir" && -d "$workdir" ]]; then
        owned_by_us=false
        for worktree_path in "${worktrees[@]}"; do
            [[ "$workdir" == "$worktree_path" ]] && owned_by_us=true && break
        done
        # An existing directory outside this repo's worktrees owns its stack.
        [[ "$owned_by_us" == "false" ]] && continue
    fi

    state="stopped"
    grep -q "^${project}-" <<< "$running_containers" && state="RUNNING"
    orphan_e2e_projects+=("$project")
    orphan_e2e_display+=("$project ($state, from ${workdir:-unknown dir})")
done <<< "$e2e_stack_labels"

if [[ ${#orphan_containers[@]} -gt 0 || ${#orphan_e2e_projects[@]} -gt 0 ]]; then
    echo ""
    echo -e "${YELLOW}Warnings:${NC}"
    for orphan in "${orphan_containers[@]}"; do
        echo "  - Orphan container: $orphan (no matching worktree)"
    done
    for orphan in "${orphan_e2e_display[@]}"; do
        echo "  - Orphan e2e stack: $orphan — no worktree .env references it"
    done
    echo ""
    if [[ ${#orphan_containers[@]} -gt 0 ]]; then
        echo "  To clean up: docker rm -f ${orphan_containers[*]}"
    fi
    for orphan in "${orphan_e2e_projects[@]}"; do
        echo "  To clean up (verify it is not in use first): docker compose -p $orphan down --volumes"
    done
fi

echo ""
