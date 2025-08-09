FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the working directory
WORKDIR /var/www/html

# Copy the application files
COPY . .

# Fix Git ownership issue
RUN git config --global --add safe.directory /var/www/html

# Set permissions for the application
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Install Symfony dependencies (optimized for production)
RUN composer install --no-scripts --optimize-autoloader

# Copy Nginx configuration
COPY docker/nginx.conf /etc/nginx/sites-available/default

# Copy entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Expose port 80 for HTTP traffic
EXPOSE 80

# Use entrypoint to run cache:clear and start services
ENTRYPOINT ["/entrypoint.sh"]