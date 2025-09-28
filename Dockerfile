FROM trafex/php-nginx:3.9.0

LABEL Maintainer="Henrik Gebauer <henrik@mind-hochschul-netzwerk.de>" \
      Description="mind-hochschul-netzwerk.de"

HEALTHCHECK --interval=10s CMD curl --silent --fail http://127.0.0.1:8080/fpm-ping

COPY --from=composer /usr/bin/composer /usr/bin/composer

USER root

# apply (security) updates
RUN set -x \
  && apk upgrade --no-cache

# install packages
RUN set -x \
  && apk add --no-cache \
       php84-ldap \
       php84-zip \
       php84-pdo_mysql \
       php84-iconv \
  && chown nobody:nobody /var/www

USER nobody

COPY config/nginx/ /etc/nginx
COPY config/php-custom.ini /etc/php84/conf.d/custom.ini
COPY --chown=nobody app/ /var/www

RUN composer install -d "/var/www/" --optimize-autoloader --no-dev --no-interaction --no-progress --no-cache

VOLUME /var/www/html/profilbilder
