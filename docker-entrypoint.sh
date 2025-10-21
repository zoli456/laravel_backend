#!/bin/bash
set -e

# Wait for PostgreSQL to be ready
echo "⏳ Waiting for database to be ready..."
until nc -z -v -w30 db 5432
do
  echo "Database not ready yet... retrying in 2s"
  sleep 2
done
echo "✅ Database is ready!"

# Run migrations and seeders
echo "🚀 Running migrations and seeders..."
php artisan migrate --force
php artisan db:seed --force

# Adjust Apache to listen on the Render port (dynamic)
if [ -n "$PORT" ]; then
  echo "🔧 Configuring Apache to listen on port $PORT..."
  sed -i "s/Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/<VirtualHost \*:.*/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

echo "✅ Starting Apache..."
exec apache2-foreground
