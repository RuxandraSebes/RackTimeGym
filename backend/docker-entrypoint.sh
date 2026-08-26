#!/bin/sh
set -e

if [ ! -f .env ]; then
  cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force
fi

attempts=0
until php artisan migrate --force; do
  attempts=$((attempts + 1))
  if [ "$attempts" -ge 30 ]; then
    echo "Database never became reachable after 60s, giving up." >&2
    exit 1
  fi
  echo "Waiting for the database to be ready..."
  sleep 2
done

exec "$@"
