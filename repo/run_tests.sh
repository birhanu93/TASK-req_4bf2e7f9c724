#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

if command -v docker &>/dev/null; then
    DOCKER_BIN="docker"
else
    echo "docker is required to run tests" >&2
    exit 1
fi

if $DOCKER_BIN compose version &>/dev/null; then
    COMPOSE=("$DOCKER_BIN" "compose")
elif command -v docker-compose &>/dev/null; then
    COMPOSE=("docker-compose")
else
    echo "docker compose plugin or docker-compose binary is required" >&2
    exit 1
fi

mkdir -p coverage

cleanup() {
    "${COMPOSE[@]}" down -v --remove-orphans >/dev/null 2>&1 || true
}
trap cleanup EXIT

"${COMPOSE[@]}" build tests
"${COMPOSE[@]}" run --rm tests "$@"
