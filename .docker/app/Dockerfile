FROM php:8.2-fpm-buster
SHELL ["/bin/bash", "-oeux", "pipefail", "-c"]

# timezone environmentdocker-compose exec app php -v
ENV TZ=Asia/Tokyo \
  # locale
  LANG=ja_JP.UTF-8 \
  LANGUAGE=ja_JP:ja \
  LC_ALL=ja_JP.UTF-8 \
  # composer environment
  COMPOSER_ALLOW_SUPERUSER=1 \
  COMPOSER_HOME=/composer



COPY --from=composer:2.0 /usr/bin/composer /usr/bin/composer

RUN apt-get update && \
  apt-get -y install git libicu-dev libonig-dev libzip-dev unzip locales libpq-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev && \
  apt-get clean && \
  rm -rf /var/lib/apt/lists/* && \
  locale-gen ja_JP.UTF-8 && \
  localedef -f UTF-8 -i ja_JP ja_JP.UTF-8 && \
  mkdir /var/run/php-fpm && \
  docker-php-ext-install intl pdo_mysql zip bcmath && \
  docker-php-ext-configure gd --with-jpeg && \
  docker-php-ext-install -j$(nproc) gd exif && \
  pecl install xdebug && \
  docker-php-ext-enable xdebug && \
  composer config -g process-timeout 3600 && \
  composer config -g repos.packagist composer https://packagist.org

COPY ./.docker/app/php-fpm.d/zzz-www.conf /usr/local/etc/php-fpm.d/zzz-www.conf
COPY ./.docker/app/php.ini /usr/local/etc/php/php.ini
COPY ./.docker/app/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

WORKDIR /work/backend
