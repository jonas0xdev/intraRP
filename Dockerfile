FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git \
    unzip \
 && rm -rf /var/lib/apt/lists/*

# Rewrite für .htaccess
RUN a2enmod rewrite

# Storage für Direktzugriff freigeben
RUN printf "Alias /storage \"/var/www/html/storage\"\n\
<Directory \"/var/www/html/storage\">\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride None\n\
    Require all granted\n\
</Directory>\n" > /etc/apache2/conf-available/storage.conf \
 && a2enconf storage

# PHP-Extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Composer reinziehen
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
