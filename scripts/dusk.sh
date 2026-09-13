#!/usr/bin/env bash
# Runs the browser tests: builds the assets, then `php artisan dusk` with .env.dusk.local.
# Dusk swaps .env for .env.dusk.local during the run and restores it afterwards, so it needs
# an .env to exist; when there is none, a temporary copy is created and removed at the end.
set -euo pipefail
cd "$(dirname "$0")/.."

created_env=false
cleanup() {
    if [ "$created_env" = true ]; then
        rm -f .env
    fi
}
trap cleanup EXIT

if [ ! -f .env ]; then
    cp .env.dusk.local .env
    created_env=true
fi

pnpm run build >/dev/null

APP_ENV=local php artisan dusk "$@"
