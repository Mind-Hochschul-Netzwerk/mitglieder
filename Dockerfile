FROM trafex/php-nginx:3.11.1

LABEL Maintainer="Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>" \
      Description="mind-hochschul-netzwerk.de"

HEALTHCHECK --interval=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping

COPY --from=composer /usr/bin/composer /usr/bin/composer

USER root

# Alpine 3.24 statt 3.23 des Basis-Images: enthält die aktuellen PHP-Sicherheitsupdates (8.5.11)
# und wird von apk upgrade mit allen übrigen (security) updates installiert
RUN set -x \
  && sed -i 's/v3\.23/v3.24/g' /etc/apk/repositories \
  && apk upgrade --no-cache --available

# install packages
RUN set -x \
  && apk add --no-cache \
       php85-ldap \
       php85-zip \
       php85-pdo_mysql \
       php85-iconv \
       php85-curl \
       php85-openssl \
  && chown nobody:nobody /var/www

USER nobody

COPY config/nginx/ /etc/nginx
COPY config/php-custom.ini /etc/php85/conf.d/custom.ini
COPY --chown=nobody app/ /var/www

RUN composer install -d "/var/www/" --optimize-autoloader --no-dev --no-interaction --no-progress --no-cache

VOLUME /var/www/profilbilder
