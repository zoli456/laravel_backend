# Use official PHP 8.3 image with Apache
FROM php:8.3-apache

# Install system dependencies, PostgreSQL extension, and utilities
RUN apt-get update && apt-get install -y \
    git unzip libpng-dev libjpeg-dev libfreetype6-dev zip libonig-dev libxml2-dev curl netcat-traditional \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# Enable Apache mod_rewrite for Laravel routing
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all project files
COPY . .

# Point Apache DocumentRoot to Laravel's public folder
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Laravel dependencies (optimized)
RUN composer install --no-dev --optimize-autoloader

# Set permissions for storage and cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port for Render or local use
ENV PORT=8080
EXPOSE 8080

# Use our entrypoint
ENTRYPOINT ["docker-entrypoint.sh"]
