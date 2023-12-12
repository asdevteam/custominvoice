# Use the official PHP image with the desired version
FROM php:8.1-apache

# Set working directory within the container
WORKDIR /var/www/html/

# Copy your existing Drupal application files to the container
COPY . /var/www/html/

# Install system dependencies and PHP extensions
RUN apt-get update && \
    apt-get install -y git \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    zip \
    unzip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install gd pdo pdo_mysql bcmath


# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    php -r "unlink('composer-setup.php');"
    
# # Install Drush
RUN composer global require drush/drush:^12.2 && \
    ln -s ~/.composer/vendor/bin/drush /usr/local/bin/drush

# Enable Apache's mod_rewrite for clean URLs
RUN a2enmod rewrite

# Copy custom Apache configuration
COPY custom-apache.conf /etc/apache2/conf-available/

# Enable the custom Apache configuration
RUN a2enconf custom-apache

# Expose port 80 for Apache
EXPOSE 80

# Start Apache server
CMD ["apache2-foreground"]
