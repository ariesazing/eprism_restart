#!/bin/sh
set -e

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
  echo "FATAL: APP_KEY is not set." >&2
  echo "Generate one locally with 'php artisan key:generate --show' and add it to the Dokploy environment." >&2
  exit 1
fi

# Recreate writable runtime dirs in case a volume is mounted over storage/
mkdir -p \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  storage/app/public \
  bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# Wait for the database to accept connections before migrating
DB_HOST_V="${DB_HOST:-127.0.0.1}"
DB_PORT_V="${DB_PORT:-3306}"
echo "Waiting for database at ${DB_HOST_V}:${DB_PORT_V} ..."
ATTEMPTS=0
until php -r '
$h = getenv("DB_HOST") ?: "127.0.0.1";
$p = getenv("DB_PORT") ?: "3306";
try {
    new PDO("mysql:host=$h;port=$p", getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "  " . $e->getMessage() . "\n");
    exit(1);
}
'; do
  ATTEMPTS=$((ATTEMPTS + 1))
  if [ "$ATTEMPTS" -ge 30 ]; then
    echo "FATAL: database not reachable after 30 attempts, giving up." >&2
    exit 1
  fi
  sleep 3
done

php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize

exec "$@"
