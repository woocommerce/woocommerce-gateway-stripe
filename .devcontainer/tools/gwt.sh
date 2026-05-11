#!/usr/bin/env bash

# Create a git worktree and open it in VS Code, or tear one down.
# Usage:
#   gwt                          # List active worktrees
#   gwt <branch> [base-branch]   # Create worktree and open it in VS Code
#   gwt done [branch]            # Remove worktree + devcontainers

set +e

DEBUG=false

WORK_ROOT="$(git rev-parse --show-toplevel 2>/dev/null)"
if [ -z "$WORK_ROOT" ]; then
  echo "Error: Not inside a git repo."
  return 1
fi
MAIN_REPO="$(git worktree list --porcelain | awk '/^worktree / {print $2; exit}')"

$DEBUG && echo "[DEBUG] → MAIN_REPO: $MAIN_REPO"
$DEBUG && echo "[DEBUG] → WORK_ROOT: $WORK_ROOT"

open_in_vscode() {
  if command -v code >/dev/null 2>&1; then
    code .
  else
    open -a "Visual Studio Code" .
  fi
}

#
# LIST ACTIVE WORKTREES
#
# $ gwt
#
if [ -z "$1" ]; then
  git worktree list
  return
fi


#
# REMOVE WORKTREE AND DEVCONTAINERS
#
# $ gwt done [branch]
#

if [[ "$1" =~ ^(rm|remove|done)$ ]]; then
  BRANCH="${2:-}"

  if [ -z "$BRANCH" ]; then
    BRANCH="$(git rev-parse --abbrev-ref HEAD)"
    WORKTREE_DIR="$WORK_ROOT"
  else
    WORKTREE_DIR="$(git -C "$MAIN_REPO" worktree list --porcelain | awk -v b="refs/heads/$BRANCH" '
      /^worktree / { wt=$2 } /^branch / && $2==b { print wt; exit }
    ')"
    if [ -z "$WORKTREE_DIR" ]; then
      echo "Error: No worktree found for branch '$BRANCH'"
      return 1
    fi
  fi

  $DEBUG && echo "[DEBUG] → BRANCH: $BRANCH"
  $DEBUG && echo "[DEBUG] → WORKTREE_DIR: $WORKTREE_DIR"

  if [ "$WORKTREE_DIR" = "$MAIN_REPO" ]; then
    echo "Error: Unable to remove main worktree ($MAIN_REPO)"
    return 1
  fi

  if [ -n "$(git -C "$WORKTREE_DIR" status --porcelain)" ]; then
    printf "The worktree has uncommitted changes; remove '%s' anyway? [y/N] " "$BRANCH"
    read -r REPLY
    case "$REPLY" in
      [yY]|[yY][eE][sS]) ;;
      *) echo "Aborted."; return 1 ;;
    esac
  fi

  echo "→ Removing devcontainers..."
  cd "$WORKTREE_DIR"
  PROJECT=$(docker ps -a --filter "label=devcontainer.local_folder=$WORKTREE_DIR" \
    --format '{{.Label "com.docker.compose.project"}}' | head -n1)
  if [ -n "$PROJECT" ]; then
    docker compose -p "$PROJECT" down -v --rmi local || true
    $DEBUG && echo "No devcontainers found, skipping..."
  fi

  echo "→ Removing worktree..."
  cd "$MAIN_REPO"
  git worktree remove --force "$WORKTREE_DIR"
  echo "✓ Done."
  return
fi


#
# CREATE WORKTREE AND OPEN IT IN VS CODE
#
# $ gwt <branch> [base-branch]
#

BRANCH="$1"
BASE="${2:-develop}"

SAFE_NAME="${BRANCH//\//-}"
WORKTREE_DIR="${MAIN_REPO}-${SAFE_NAME}"

if [ -d "$WORKTREE_DIR" ]; then
  echo "→ Worktree exists, changing dirs and opening VS Code"
  cd "$WORKTREE_DIR"
  open_in_vscode
  return 0
fi

cd "$MAIN_REPO"
git fetch origin

if git show-ref --verify --quiet "refs/heads/$BRANCH" || git ls-remote --exit-code --heads origin "$BRANCH" >/dev/null 2>&1; then
  HUSKY=0 git worktree add "$WORKTREE_DIR" "$BRANCH" || true
else
  printf "Create new branch '%s' from 'origin/%s'? [y/N] " "$BRANCH" "$BASE"
  read -r REPLY
  case "$REPLY" in
    [yY]|[yY][eE][sS])
      HUSKY=0 git worktree add --no-track "$WORKTREE_DIR" -b "$BRANCH" "origin/$BASE" || true
      ;;
    *)
      echo "Aborted."
      return 1
      ;;
  esac
fi

if [ ! -d "$WORKTREE_DIR" ]; then
  echo "Error: Worktree was not created at $WORKTREE_DIR"
  return 1
fi

cd "$WORKTREE_DIR"

echo "✓ Done."
open_in_vscode
