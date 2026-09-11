#!/bin/bash
# bin/docker-port-setup.sh
# Ensures .env exists with WORDPRESS_PORT, WORKTREE_ID, and an isolated
# E2E_PROJECT/E2E_WP_PORT/E2E_DB_PORT trio for the Docker e2e stack.

set -e

ENV_FILE=".env"
PORT_RANGE_START=8170
PORT_RANGE_END=8189
MAIN_CHECKOUT_DEFAULT_PORT=8072
# The historical machine-global e2e stack (E2E_DEFAULT_*), kept as the main
# checkout's stack so CI and existing single-checkout usage are unchanged.
. "$(dirname "${BASH_SOURCE[0]}")/../tests/e2e/bin/e2e-stack-defaults.sh"
# Dedicated ranges for per-worktree e2e stacks, disjoint from the dev-site
# range above and from the main checkout's e2e defaults.
E2E_WP_PORT_RANGE_START=8290
E2E_WP_PORT_RANGE_END=8309
E2E_DB_PORT_RANGE_START=6800
E2E_DB_PORT_RANGE_END=6819
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
    local key=${1:-WORDPRESS_PORT}
    while IFS= read -r line; do
        if [[ $line =~ ^worktree\ (.+)$ ]]; then
            local dir="${BASH_REMATCH[1]}"
            [[ "$dir" == "$CURRENT_DIR" ]] && continue
            if [[ -f "$dir/.env" ]]; then
                grep "^${key}=" "$dir/.env" 2>/dev/null | cut -d= -f2
            fi
        fi
    done < <(git worktree list --porcelain 2>/dev/null)
}

# Pick the first port in [$2, $3] that is neither reserved by another
# worktree's .env (key $1) nor currently bound on the host. The chosen port is
# the function's stdout, so progress messages go to stderr.
find_free_port() {
    local key=$1 range_start=$2 range_end=$3 port
    local reserved=" $(get_reserved_ports "$key" | tr '\n' ' ')"
    for port in $(seq "$range_start" "$range_end"); do
        if [[ "$reserved" == *" $port "* ]] || [[ "$reserved" == *" $port" ]]; then
            echo "  Port $port reserved by another worktree, skipping..." >&2
            continue
        fi
        if ! lsof -i ":$port" > /dev/null 2>&1; then
            echo "$port"
            return 0
        fi
    done
    return 1
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
        WORDPRESS_PORT=$(find_free_port WORDPRESS_PORT $PORT_RANGE_START $PORT_RANGE_END) || {
            echo "Error: No available ports in range $PORT_RANGE_START-$PORT_RANGE_END"
            exit 1
        }
        echo "WORDPRESS_PORT=$WORDPRESS_PORT" >> "$ENV_FILE"
        echo "Set WORDPRESS_PORT=$WORDPRESS_PORT"
    fi
fi

# The e2e Docker stack's compose project name and host ports are machine-global
# (see tests/e2e/bin/common.sh), so give each worktree its own trio. The main
# checkout keeps the historical defaults so existing usage is unchanged.
if [[ -z "$E2E_PROJECT" ]]; then
    if is_main_checkout; then
        E2E_PROJECT="$E2E_DEFAULT_PROJECT"
    else
        E2E_PROJECT="$E2E_DEFAULT_PROJECT-$WORKTREE_ID"
    fi
    echo "E2E_PROJECT=$E2E_PROJECT" >> "$ENV_FILE"
    echo "Set E2E_PROJECT=$E2E_PROJECT"
fi

if [[ -z "$E2E_WP_PORT" ]]; then
    if is_main_checkout; then
        E2E_WP_PORT=$E2E_DEFAULT_WP_PORT
    else
        E2E_WP_PORT=$(find_free_port E2E_WP_PORT $E2E_WP_PORT_RANGE_START $E2E_WP_PORT_RANGE_END) || {
            echo "Error: No available e2e ports in range $E2E_WP_PORT_RANGE_START-$E2E_WP_PORT_RANGE_END"
            exit 1
        }
    fi
    echo "E2E_WP_PORT=$E2E_WP_PORT" >> "$ENV_FILE"
    echo "Set E2E_WP_PORT=$E2E_WP_PORT"
fi

if [[ -z "$E2E_DB_PORT" ]]; then
    if is_main_checkout; then
        E2E_DB_PORT=$E2E_DEFAULT_DB_PORT
    else
        E2E_DB_PORT=$(find_free_port E2E_DB_PORT $E2E_DB_PORT_RANGE_START $E2E_DB_PORT_RANGE_END) || {
            echo "Error: No available e2e DB ports in range $E2E_DB_PORT_RANGE_START-$E2E_DB_PORT_RANGE_END"
            exit 1
        }
    fi
    echo "E2E_DB_PORT=$E2E_DB_PORT" >> "$ENV_FILE"
    echo "Set E2E_DB_PORT=$E2E_DB_PORT"
fi

# Guard against a .env that was copied from the main checkout instead of
# generated here. Such a file still holds the main checkout's values (dev-site
# port 8072, e2e stack wcstripe-e2e/8088/6789), so this worktree's `npm run
# up` and e2e commands would operate on the main checkout's containers and
# ports instead of its own. This script never writes those values into a
# worktree, so any of them appearing here means the file was copied; collect
# the offending keys and warn, rather than silently rewriting a file the user
# may have pointed at the main stack on purpose.
if ! is_main_checkout; then
    copied_defaults=()
    [[ "$WORDPRESS_PORT" == "$MAIN_CHECKOUT_DEFAULT_PORT" ]] && copied_defaults+=("WORDPRESS_PORT=$WORDPRESS_PORT")
    [[ "$E2E_PROJECT" == "$E2E_DEFAULT_PROJECT" ]] && copied_defaults+=("E2E_PROJECT=$E2E_PROJECT")
    [[ "$E2E_WP_PORT" == "$E2E_DEFAULT_WP_PORT" ]] && copied_defaults+=("E2E_WP_PORT=$E2E_WP_PORT")
    [[ "$E2E_DB_PORT" == "$E2E_DEFAULT_DB_PORT" ]] && copied_defaults+=("E2E_DB_PORT=$E2E_DB_PORT")
    if [[ ${#copied_defaults[@]} -gt 0 ]]; then
        echo "WARNING: .env holds main-checkout defaults (${copied_defaults[*]})."
        echo "         This worktree would clobber the main checkout's containers and ports."
        echo "         If this .env was copied from the main checkout, run:"
        echo "           rm .env && npm run worktree:setup"
    fi
fi

echo "Using WORKTREE_ID=$WORKTREE_ID, WORDPRESS_PORT=$WORDPRESS_PORT, E2E_PROJECT=$E2E_PROJECT (ports $E2E_WP_PORT/$E2E_DB_PORT)"
