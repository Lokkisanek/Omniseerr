#!/bin/sh
set -e

CONFIG_DIR="${CONFIG_DIR:-/config}"
APP_DIR="/var/www/html"

mkdir -p "$CONFIG_DIR" \
    "$APP_DIR/storage/framework/cache" \
    "$APP_DIR/storage/framework/sessions" \
    "$APP_DIR/storage/framework/views" \
    "$APP_DIR/storage/logs" \
    "$APP_DIR/bootstrap/cache"

# Persist APP_KEY across container rebuilds so encrypted service credentials stay readable.
if [ -f "$CONFIG_DIR/app.key" ]; then
    export APP_KEY="$(cat "$CONFIG_DIR/app.key")"
elif [ -n "${APP_KEY:-}" ]; then
    printf '%s' "$APP_KEY" > "$CONFIG_DIR/app.key"
else
    KEY="$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")"
    printf '%s' "$KEY" > "$CONFIG_DIR/app.key"
    export APP_KEY="$KEY"
fi

export APP_NAME="${APP_NAME:-Omniseerr}"
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export APP_URL="${APP_URL:-http://localhost:5056}"
export DB_CONNECTION=sqlite
export DB_DATABASE="${DB_DATABASE:-$CONFIG_DIR/omniseerr.sqlite}"
export DB_JOURNAL_MODE=WAL
export DB_BUSY_TIMEOUT=5000
export SESSION_DRIVER="${SESSION_DRIVER:-file}"
export CACHE_STORE="${CACHE_STORE:-file}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export BROADCAST_CONNECTION=reverb
export REVERB_APP_ID="${REVERB_APP_ID:-omniseerr}"
export REVERB_APP_KEY="${REVERB_APP_KEY:-omniseerr-key}"
export REVERB_APP_SECRET="${REVERB_APP_SECRET:-omniseerr-secret}"
export REVERB_HOST="${REVERB_HOST:-localhost}"
export REVERB_PORT="${REVERB_PORT:-8080}"
export REVERB_SCHEME="${REVERB_SCHEME:-http}"
export REVERB_SERVER_HOST=0.0.0.0
export REVERB_SERVER_PORT=8080
export VITE_REVERB_APP_KEY="$REVERB_APP_KEY"
export VITE_REVERB_HOST="$REVERB_HOST"
export VITE_REVERB_PORT="$REVERB_PORT"
export VITE_REVERB_SCHEME="$REVERB_SCHEME"
export LOG_CHANNEL=stderr

touch "$DB_DATABASE"

# Write a runtime .env so artisan/config:cache can resolve values consistently.
cat > "$APP_DIR/.env" <<EOF
APP_NAME=${APP_NAME}
APP_ENV=${APP_ENV}
APP_KEY=${APP_KEY}
APP_DEBUG=${APP_DEBUG}
APP_URL=${APP_URL}
DB_CONNECTION=sqlite
DB_DATABASE=${DB_DATABASE}
DB_JOURNAL_MODE=WAL
DB_BUSY_TIMEOUT=5000
SESSION_DRIVER=${SESSION_DRIVER}
CACHE_STORE=${CACHE_STORE}
QUEUE_CONNECTION=${QUEUE_CONNECTION}
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_HOST=${REVERB_HOST}
REVERB_PORT=${REVERB_PORT}
REVERB_SCHEME=${REVERB_SCHEME}
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${REVERB_HOST}
VITE_REVERB_PORT=${REVERB_PORT}
VITE_REVERB_SCHEME=${REVERB_SCHEME}
LOG_CHANNEL=stderr
EOF

chown -R www-data:www-data "$CONFIG_DIR" "$APP_DIR/storage" "$APP_DIR/bootstrap/cache" "$APP_DIR/.env"

php artisan migrate --force --no-interaction
php artisan config:clear
php artisan route:clear
php artisan view:clear

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
