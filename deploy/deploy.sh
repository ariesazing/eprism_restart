#!/usr/bin/env bash
# Executed on the VPS by the GitHub Actions deploy workflow over SSH.
# Assumes the repo is already cloned at APP_DIR and .env already exists there.

set -euo pipefail

APP_DIR="${1:?Usage: deploy.sh /path/to/app}"
cd "$APP_DIR"

echo "==> Pulling latest code"
git fetch --depth=1 origin master
git reset --hard origin/master

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Installing JS dependencies and building assets"
npm ci
npm run build

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Caching configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Restarting queue worker and Reverb"
sudo supervisorctl restart eprism-queue:* eprism-reverb:*

echo "==> Reloading PHP-FPM"
sudo systemctl reload php8.2-fpm

echo "==> Deploy complete"
