#!/bin/bash
# bin/docker-port-setup.sh
# Ensures .env exists with WORDPRESS_PORT and WORKTREE_ID.

set -e

ENV_FILE=".env"
PORT_RANGE_START=8170
PORT_RANGE_END=8189
MAIN_CHECKOUT_DEFAULT_PORT=8072
CURRENT_DIR="$(pwd)"

is_main_checkout() {
    if ! git rev-parse --is-inside-work-tree &>/dev/null; then
        return 0
    fi
    local git_dir git_common_dir
    git_dir=$(git rev-parse --git-dir)
    git_common_dir=$(git rev-parse --git-common-dir)
    [[ "$git_dir" == "$git_common_dir" ]]
}

get_reserved_ports() {
    # Parse `git worktree list --porcelain` (each worktree starts with `worktree <path>`)
    # so paths containing spaces are handled correctly.
    while IFS= read -r line; do
        if [[ $line =~ ^worktree\ (.+)$ ]]; then
            local dir="${BASH_REMATCH[1]}"
            [[ "$dir" == "$CURRENT_DIR" ]] && continue
            if [[ -f "$dir/.env" ]]; then
                grep '^WORDPRESS_PORT=' "$dir/.env" 2>/dev/null | cut -d= -f2
            fi
        fi
    done < <(git worktree list --porcelain 2>/dev/null)
}

DEFAULT_WORKTREE_ID=$(basename "$(pwd)" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g')

if [[ -f "$ENV_FILE" ]]; then
    source "$ENV_FILE"
fi

if [[ -z "$WORKTREE_ID" ]]; then
    if is_main_checkout; then
        WORKTREE_ID="default"
    else
        WORKTREE_ID="$DEFAULT_WORKTREE_ID"
    fi
    echo "WORKTREE_ID=$WORKTREE_ID" >> "$ENV_FILE"
    echo "Set WORKTREE_ID=$WORKTREE_ID"
fi

if [[ -z "$WORDPRESS_PORT" ]]; then
    if is_main_checkout; then
        WORDPRESS_PORT=$MAIN_CHECKOUT_DEFAULT_PORT
        echo "WORDPRESS_PORT=$WORDPRESS_PORT" >> "$ENV_FILE"
        echo "Set WORDPRESS_PORT=$WORDPRESS_PORT (main checkout default)"
    else
        echo "Scanning for available port in $PORT_RANGE_START-$PORT_RANGE_END..."
        RESERVED_PORTS=" $(get_reserved_ports | tr '\n' ' ')"
        for port in $(seq $PORT_RANGE_START $PORT_RANGE_END); do
            if [[ "$RESERVED_PORTS" == *" $port "* ]] || [[ "$RESERVED_PORTS" == *" $port" ]]; then
                echo "  Port $port reserved by another worktree, skipping..."
                continue
            fi
            if ! lsof -i ":$port" > /dev/null 2>&1; then
                WORDPRESS_PORT=$port
                break
            fi
        done

        if [[ -z "$WORDPRESS_PORT" ]]; then
            echo "Error: No available ports in range $PORT_RANGE_START-$PORT_RANGE_END"
            exit 1
        fi

        echo "WORDPRESS_PORT=$WORDPRESS_PORT" >> "$ENV_FILE"
        echo "Set WORDPRESS_PORT=$WORDPRESS_PORT"
    fi
fi

echo "Using WORKTREE_ID=$WORKTREE_ID, WORDPRESS_PORT=$WORDPRESS_PORT"
