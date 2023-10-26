FROM composer:latest AS composer_stage
RUN rm -rf /var/app && mkdir /var/app
WORKDIR /var/app
COPY . /var/app
RUN composer install --ignore-platform-reqs --prefer-dist --no-scripts
RUN composer dump-autoload --optimize --apcu --no-dev

FROM node:latest AS npm_stage
COPY --from=composer_stage /var/app /var/app
WORKDIR /var/app
RUN npm install

FROM php:fpm AS php_stage
COPY --from=npm_stage /var/app /var/app
WORKDIR /var/app
RUN apt-get update && apt-get install -y zlib1g-dev libicu-dev g++
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl
RUN docker-php-ext-install mysqli pdo pdo_mysql intl

