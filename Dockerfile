FROM php:7.2-fpm

RUN apt-get update \
  && apt-get -y install \
    git \
    libpng-dev \
    unzip \
    wget \
    zip \
 && rm -rf /var/lib/apt/lists/*

# Install PHP extensions.
RUN docker-php-ext-install gd
RUN docker-php-ext-install zip

# Install Composer.
RUN curl -sS https://getcomposer.org/installer | php -- \
  --filename=composer --install-dir=/usr/local/bin

RUN touch /var/log/php5-fpm.log && \
  chown www-data:www-data /var/log/php5-fpm.log && \
  chown -R www-data:www-data /var/www

WORKDIR /usr/local/src/arc

USER www-data
