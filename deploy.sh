#!/usr/bin/env bash

set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/lawdocs}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

cd "$APP_DIR"

if [[ ! -f artisan || ! -f composer.json || ! -f .env ]]; then
    echo "Error: $APP_DIR is not a configured Laravel application (.env is required)." >&2
    exit 1
fi

maintenance_enabled=false

finish() {
    exit_code=$?

    if [[ "$maintenance_enabled" == true ]]; then
        "$PHP_BIN" artisan up || true
    fi

    if (( exit_code != 0 )); then
        echo "Deployment failed (exit code $exit_code)." >&2
    fi
}

trap finish EXIT

echo "Deploying $BRANCH to $APP_DIR..."

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git merge --ff-only "origin/$BRANCH"

if [[ -f package-lock.json ]]; then
    if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
        echo "Error: Node.js and npm are required to build frontend assets." >&2
        echo "Install Node.js 22 LTS on this server, then run ./deploy.sh again." >&2
        exit 1
    fi

    if ! node -e 'const [major, minor] = process.versions.node.split(".").map(Number); process.exit(major > 22 || major === 22 && minor >= 12 || major === 20 && minor >= 19 ? 0 : 1)'; then
        echo "Error: Node.js 20.19+ or 22.12+ is required (found $(node --version))." >&2
        echo "Install Node.js 22 LTS on this server, then run ./deploy.sh again." >&2
        exit 1
    fi
fi

"$PHP_BIN" artisan down --retry=60
maintenance_enabled=true

COMPOSER_ALLOW_SUPERUSER=1 "$COMPOSER_BIN" install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

if [[ -f package-lock.json ]]; then
    npm ci
    npm run build
fi

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force
"$PHP_BIN" artisan storage:link
"$PHP_BIN" artisan optimize
"$PHP_BIN" artisan queue:restart

nginx -t
systemctl restart nginx

"$PHP_BIN" artisan up
maintenance_enabled=false

echo "Deployment completed successfully."
