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

"$PHP_BIN" artisan down --retry=60
maintenance_enabled=true

"$COMPOSER_BIN" install \
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
