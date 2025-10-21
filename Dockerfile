# -------------------------------
# 1. Base image
# -------------------------------
FROM php:8.2-apache

# -------------------------------
# 2. Set working directory
# -------------------------------
WORKDIR /var/www/html

# -------------------------------
# 3. Install system dependencies
# -------------------------------
RUN apt-get update && apt-get install -y \
    git unzip libpng-dev libjpeg-dev libfreetype6-dev zip libonig-dev libxml2-dev curl \
    libpq-dev netcat-traditional \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd

# -------------------------------
# 4. Enable Apache rewrite module
# -------------------------------
RUN a2enmod rewrite

# -------------------------------
# 5. Copy Laravel app
# -------------------------------
COPY . /var/www/html

# -------------------------------
# 6. Install Composer dependencies
# -------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# -------------------------------
# 7. Fix permissions
# -------------------------------
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# -------------------------------
# 8. Environment setup
# -------------------------------
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=10000

# -------------------------------
# 9. Apache configuration for Render
# -------------------------------
RUN sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf && \
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${PORT}>/" /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

# -------------------------------
# 10. Copy entrypoint script
# -------------------------------
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# -------------------------------
# 11. Set entrypoint
# -------------------------------
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# -------------------------------
# 12. Start Apache
# -------------------------------
CMD ["apache2-foreground"]
