FROM php:7.4-fpm

RUN ln -sf /usr/share/zoneinfo/Asia/Shanghai /etc/localtime \
    && echo "Asia/Shanghai" > /etc/timezone

RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        unzip \
        git \
        cron \
        default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql zip gd opcache \
    && pecl install redis-5.3.7 \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY services/php/app/ .

RUN composer install --no-dev --optimize-autoloader \
    && mkdir -p runtime \
    && chown -R www-data:www-data runtime

# 日志清理脚本
COPY services/php/cleanup-logs.sh /usr/local/bin/cleanup-logs.sh
RUN chmod +x /usr/local/bin/cleanup-logs.sh

# 定时任务
COPY services/php/crontab /var/spool/cron/crontabs/www-data
RUN chown www-data:crontab /var/spool/cron/crontabs/www-data \
    && chmod 600 /var/spool/cron/crontabs/www-data

# PHP 配置
COPY services/php/conf/php.ini /usr/local/etc/php/conf.d/custom.ini
# PHP-FPM 配置
COPY services/php/conf/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY services/php/conf/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY services/php/conf/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# 同时运行 cron 和 php-fpm
COPY services/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

EXPOSE 9000
