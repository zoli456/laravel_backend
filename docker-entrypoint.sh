#!/bin/bash
set -e

# Set Apache ServerName to suppress warnings
echo "🔧 Setting Apache ServerName..."
echo "ServerName localhost" >> /etc/apache2/apache2.conf

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

# Run migrations and seeders
echo "🚀 Running migrations and seeders..."
php artisan migrate --force
php artisan db:seed --force

# Adjust Apache to listen on the Render port
if [ -n "$PORT" ]; then
  echo "🔧 Configuring Apache to listen on port $PORT..."
  sed -i "s/Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/<VirtualHost \*:.*/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# Ensure Apache serves Laravel's public directory
if [ -d "/var/www/html/public" ]; then
  echo "📂 Setting Apache DocumentRoot to /var/www/html/public"
  sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|' /etc/apache2/sites-available/000-default.conf
fi

echo "✅ Starting Apache..."
exec apache2-foreground
