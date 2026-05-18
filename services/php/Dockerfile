FROM php:7.3-fpm

RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_mysql zip \
    && pecl install redis-5.3.7 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY app/ .

RUN composer install --no-dev --optimize-autoloader \
    && mkdir -p runtime \
    && chown -R www-data:www-data runtime

EXPOSE 9000
