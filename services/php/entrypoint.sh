#!/bin/bash

# 确保 uploads 目录存在且 www-data 可写
mkdir -p /var/www/html/uploads/avatars
mkdir -p /var/www/html/uploads/attachments
chown -R www-data:www-data /var/www/html/uploads

# 开发环境(APP_DEBUG=1): 开启文件变更检测，改代码即时生效
# 生产环境(APP_DEBUG=0): 关闭检测，最大性能，部署后需 restart
if [ "${APP_DEBUG:-0}" = "1" ]; then
    sed -i 's/opcache.validate_timestamps=0/opcache.validate_timestamps=1/' \
        /usr/local/etc/php/conf.d/custom.ini
    sed -i 's/opcache.enable_file_override=1/opcache.enable_file_override=0/' \
        /usr/local/etc/php/conf.d/custom.ini
fi

cron
exec php-fpm
