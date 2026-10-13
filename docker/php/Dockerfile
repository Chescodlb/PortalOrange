FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libicu-dev \
        libxml2-dev libzip-dev libxslt1-dev libldap2-dev \
        libcurl4-openssl-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j2 \
        bcmath curl exif gd intl ldap mbstring mysqli opcache pdo_mysql soap xsl zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/orange.ini
COPY apache.conf /etc/apache2/conf-available/orange.conf
RUN a2enconf orange
