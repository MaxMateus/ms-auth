FROM php:8.2-fpm

# system deps
RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libonig-dev libxml2-dev curl

# php extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# permissoes
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
