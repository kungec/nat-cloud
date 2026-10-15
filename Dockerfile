FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip opcache pcntl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=24M'; \
        echo 'expose_php=Off'; \
    } > /usr/local/etc/php/conf.d/natcloud.ini

WORKDIR /var/www/html
COPY public/ /var/www/html/
COPY app/ /var/www/html/app/
COPY cron/ /var/www/html/cron/

RUN mkdir -p /var/www/html/data/cache /var/www/html/data/upload \
    && chown -R www-data:www-data /var/www/html/data

RUN { \
        echo '<VirtualHost *:80>'; \
        echo '  DocumentRoot /var/www/html'; \
        echo '  <Directory /var/www/html>'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '  </Directory>'; \
        echo '</VirtualHost>'; \
    } > /etc/apache2/sites-available/000-default.conf

EXPOSE 80
