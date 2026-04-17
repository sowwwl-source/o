FROM php:8.3-apache

WORKDIR /var/www/html

RUN a2enmod headers rewrite

COPY deploy/app/security.conf /etc/apache2/conf-enabled/zzz-sowwwl-security.conf
COPY deploy/app/entrypoint.sh /usr/local/bin/sowwwl-entrypoint

COPY index.php land.php config.php styles.css main.js manifest.json site-sw.js favicon.svg ./
COPY lib ./lib
COPY icons ./icons

RUN chmod +x /usr/local/bin/sowwwl-entrypoint

ENTRYPOINT ["sowwwl-entrypoint"]
CMD ["apache2-foreground"]
