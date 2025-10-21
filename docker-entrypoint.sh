#!/bin/bash
set -e

echo "🚀 Starting Laravel container..."

# Wait for PostgreSQL database to be ready
if [ -n "$DB_HOST" ] && [ -n "$DB_PORT" ]; then
  echo "⏳ Waiting for PostgreSQL at $DB_HOST:$DB_PORT..."
  for i in {1..10}; do
    if nc -z "$DB_HOST" "$DB_PORT"; then
      echo "✅ PostgreSQL is ready!"
      break
    fi
    echo "Database not ready yet... retrying in 3s"
    sleep 3
  done
else
  echo "⚠️ No DB_HOST or DB_PORT defined, skipping database wait..."
fi

# Run migrations automatically
echo "🛠️ Running migrations..."
php artisan migrate --force || echo "⚠️ Migration failed (check DB connection)."

# Seed the database
echo "🌱 Seeding database..."
php artisan db:seed --force || echo "⚠️ Seeding failed (check DB connection or seeder setup)."

# Clear and cache Laravel configs for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Apache
echo "🌐 Starting Apache..."
exec apache2-foreground
