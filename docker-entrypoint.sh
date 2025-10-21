#!/bin/bash
set -e

echo "🚀 Starting Laravel container..."

# Wait for the database to be ready (5 retries, 2 seconds each)
if [ -n "$DB_HOST" ]; then
  echo "⏳ Waiting for database ($DB_HOST:$DB_PORT)..."
  for i in {1..5}; do
    if nc -z "$DB_HOST" "$DB_PORT"; then
      echo "✅ Database is up!"
      break
    fi
    echo "Database not ready yet... retrying in 2s"
    sleep 2
  done
else
  echo "⚠️ No DB_HOST defined, skipping wait..."
fi

# Run migrations automatically
echo "🛠️ Running migrations..."
php artisan migrate --force || echo "⚠️ Migration failed (check DB connection)."

# Clear and cache Laravel configs
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache
echo "🌐 Starting Apache..."
exec apache2-foreground
