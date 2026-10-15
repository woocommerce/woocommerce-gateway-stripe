#!/bin/bash

# Load local worktree variables from .env when present.
source .env 2>/dev/null

# Set the container name from the worktree variable (when available).
CONTAINER=wcstripe_wp_${WORKTREE_ID:-default}

# Run the WordPress CLI container with the arguments passed through.
docker run -it --env-file default.env --rm \
	--volumes-from $CONTAINER \
	--network container:$CONTAINER \
	wordpress:cli $@
