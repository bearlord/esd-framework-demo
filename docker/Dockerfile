FROM php:7.4.21-cli
MAINTAINER bearlord

RUN apt-get update  && \
    apt-get install libbz2-dev libzip-dev libssl-dev procps unzip git-core libfreetype6-dev libjpeg62-turbo-dev libmcrypt-dev libpng-dev libpq-dev librabbitmq-dev -y && \
    docker-php-source extract && \
    docker-php-ext-install bcmath bz2 pcntl exif pdo_mysql pdo_pgsql zip gd sockets && \
    pecl install amqp && docker-php-ext-enable amqp && \
    pecl install inotify  && docker-php-ext-enable inotify && \
    pecl install redis && docker-php-ext-enable redis &&  \

	curl -sfL http://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && \
    chmod +x /usr/bin/composer                                                                     && \

    docker-php-ext-install sockets && \
    docker-php-source extract && \
    mkdir /usr/src/php/ext/swoole && \
    curl -sfL https://github.com/swoole/swoole-src/archive/v4.5.9.tar.gz -o swoole.tar.gz && \
    tar xfz swoole.tar.gz --strip-components=1 -C /usr/src/php/ext/swoole && \
    docker-php-ext-configure swoole --enable-http2 --enable-mysqlnd --enable-openssl --enable-sockets --enable-swoole-json && \
    docker-php-ext-install -j$(nproc) swoole && \
    rm -f swoole.tar.gz $HOME/.composer/*-old.phar && \
    docker-php-source delete && \

    apt-get autoclean &&  \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

VOLUME ["/data"]

WORKDIR /data

CMD ["/bin/bash"]
