#!/usr/bin/env bash

docker volume create devcontainer_claude_shared >/dev/null
docker volume create devcontainer_codex_shared >/dev/null
docker volume create devcontainer_shell_history >/dev/null
