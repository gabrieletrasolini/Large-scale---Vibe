FROM php:8.2-apache

# Install MongoDB extension via PECL
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

# Abilita mod_rewrite (se serve)
RUN a2enmod rewrite

WORKDIR /var/www/html
