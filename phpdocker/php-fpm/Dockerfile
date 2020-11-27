FROM phpdockerio/php74-fpm:latest
WORKDIR "/application"

# Install selected extensions and other stuff
RUN apt-get update \
    && apt-get -y install libssl-dev \
    && apt-get -y install php-pear \
    && apt-get -y install libcurl3-openssl-dev \
    && apt-get -y install librabbitmq-dev \
    && apt-get -y --no-install-recommends install php7.4-memcached php7.4-mysql php7.4-gd php7.4-mbstring php7.4-ssh2 php7.4-xdebug php7.4-dev cron \
    && apt-get -y install make \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* \
    && pecl install amqp

# Install git
RUN apt-get update \
    && apt-get -y install git \
    && apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Fix debconf warnings upon build
ARG DEBIAN_FRONTEND=noninteractive
