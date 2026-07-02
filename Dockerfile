FROM php:8.2-apache

# Install PostgreSQL extensions + utilities
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite & headers
RUN a2enmod rewrite headers

# PHP production ini
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i 's/display_errors = On/display_errors = Off/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/expose_php = On/expose_php = Off/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 10M/' "$PHP_INI_DIR/php.ini" \
    && sed -i 's/post_max_size = 8M/post_max_size = 12M/' "$PHP_INI_DIR/php.ini"

# Enable .htaccess & AllowOverride for security config
RUN echo '<Directory /var/www/html>\n    AllowOverride All\n    Options -Indexes\n    Require all granted\n</Directory>' > /etc/apache2/conf-available/app-security.conf \
    && a2enconf app-security

# Apache security config
RUN echo 'ServerTokens Prod' >> /etc/apache2/apache2.conf \
    && echo 'ServerSignature Off' >> /etc/apache2/apache2.conf \
    && echo 'TraceEnable Off' >> /etc/apache2/apache2.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Remove dev-only files
RUN rm -rf \
    node_modules \
    cypress \
    cypress.config.js \
    package.json \
    package-lock.json \
    scratch \
    scratch_check_ids.php \
    scratch_check_schedule.php \
    cypress_reset_db.php \
    .env.example \
    README.md \
    workflow.php

# Create uploads dir if it doesn't exist
RUN mkdir -p /var/www/html/uploads

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod 775 /var/www/html/uploads

EXPOSE 80
