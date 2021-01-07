FROM golang:1.14 as cfssl
WORKDIR /workdir
RUN git clone https://github.com/cloudflare/cfssl.git /workdir && \
    git clone https://github.com/cloudflare/cfssl_trust.git /etc/cfssl && \
    make clean && \
    make bin/rice && ./bin/rice embed-go -i=./cli/serve && \
    make all && cp bin/* /usr/bin/
RUN mkdir /home/cfssl/ && \
    chown 1000:1000 /home/cfssl/
EXPOSE 8888

FROM nextcloud:stable-fpm as prod
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN apt update && apt install -y git
RUN apt update && apt install -y cmake poppler-data libopenjp2-7-dev libfreetype6-dev libfontconfig1-dev libjpeg-dev libtiff5-dev libnss3-dev \
    && curl https://poppler.freedesktop.org/poppler-20.08.0.tar.xz -o /tmp/poppler-20.08.0.tar.xz \
    && cd /tmp && tar -xf poppler-20.08.0.tar.xz \
    && cd poppler-20.08.0 && mkdir build && cd build \
    && cmake .. -DBUILD_GTK_TESTS=OFF -DBUILD_QT5_TESTS=OFF -DBUILD_QT6_TESTS=OFF -DBUILD_CPP_TESTS=OFF -DENABLE_SPLASH=OFF -DENABLE_CPP=OFF -DENABLE_GLIB=OFF -DENABLE_GOBJECT_INTROSPECTION=OFF -DENABLE_QT5=OFF -DENABLE_QT6=OFF -DENABLE_CMS=lcms2 -DENABLE_LIBOPENJPEG=openjpeg2 \
    && make
RUN mkdir /tmp/cfssl/ && \
    chmod 777 /tmp/cfssl/
COPY --from=cfssl /workdir/bin /usr/bin

FROM prod as dev
RUN yes | pecl install xdebug-2.9.6
RUN echo "" > /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_port=9001" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_enable=1" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_connect_back=1" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.remote_host=network_database" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.profiler_enable=1" >> /usr/local/etc/php/conf.d/xdebug.ini


