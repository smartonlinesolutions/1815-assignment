FROM php:8.3-fpm-bookworm

ARG HOST_UID=1000
ARG HOST_GID=1000

COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip curl gosu default-mysql-client \
 && rm -rf /var/lib/apt/lists/* \
 && install-php-extensions mysqli pdo_mysql opcache intl zip gd exif bcmath

# WP-CLI
RUN curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
 && chmod +x /usr/local/bin/wp

# A user that matches your host user, so bind-mounted files keep the right owner.
RUN groupadd -o -g "${HOST_GID}" app \
 && useradd -o -m -u "${HOST_UID}" -g app -s /bin/sh app

ENV COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

COPY php.ini /usr/local/etc/php/conf.d/zz-havenpost.ini
COPY www.conf /usr/local/etc/php-fpm.d/zz-havenpost.conf
COPY scaffold /opt/scaffold
COPY app-init.sh /usr/local/bin/app-init
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/app-init /usr/local/bin/entrypoint

WORKDIR /var/www/app

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
