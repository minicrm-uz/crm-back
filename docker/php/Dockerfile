FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    postgresql-dev \
    autoconf \
    g++ \
    make \
 && docker-php-ext-install pdo pdo_pgsql pgsql mbstring exif pcntl bcmath gd \
 && apk del autoconf g++ make

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN addgroup -g 1000 app && adduser -u 1000 -G app -s /bin/sh -D app \
 && mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache \
 && chown -R app:app /var/www/html

USER app

EXPOSE 9000
CMD ["php-fpm"]
