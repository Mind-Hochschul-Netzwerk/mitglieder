FROM mindhochschulnetzwerk/php-base

LABEL Maintainer="Henrik Gebauer <code@henrik-gebauer.de>" \
      Description="mind-hochschul-netzwerk.de"

ARG COMPOSER_VERSION="1.10.17"
ARG COMPOSER_SHA256SUM="6fa00eba5103ce6750f94f87af8356e12cc45d5bbb11a140533790cf60725f1c"

COPY entry.d/ /entry.d/
COPY update.d/ /update.d/
COPY app/ /var/www/

RUN set -ex \
  && apk --no-cache add \
    php7-mysqli \
    php7-xml \
    php7-zip \
    php7-curl \
    php7-gd \
    php7-mbstring \
    php7-ldap \  
    php7-phar \  
    php7-json \
    php7-session \
    php7-ctype \
  # begin: composer
  && php -r "readfile('https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar');" > /usr/local/bin/composer \
  && test "$(sha256sum /usr/local/bin/composer|cut -d' ' -f1)" = "$COMPOSER_SHA256SUM" \
  && chmod a+x /usr/local/bin/composer \
  && mkdir /var/www/vendor && chown www-data:www-data /var/www/vendor \
  && cd /var/www \
  && su www-data -s /bin/sh -c "composer install --no-dev --no-cache" \
  && apk --no-cache del php7-phar \
  && rm /usr/local/bin/composer \
  # end: composer
  && chown -R nobody:nobody /var/www \
  && mkdir -p /var/www/html/profilbilder && chown www-data:www-data /var/www/html/profilbilder

VOLUME /var/www/html/profilbilder
