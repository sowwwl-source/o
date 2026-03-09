FROM php:8.2-apache

RUN a2enmod headers rewrite && \
    printf '%s\n' \
      '<Directory /var/www/html>' \
      '  AllowOverride All' \
      '  Require all granted' \
      '</Directory>' \
      > /etc/apache2/conf-available/sowwwl-api.conf && \
    a2enconf sowwwl-api

WORKDIR /var/www/html

RUN mkdir -p /var/www/html/docs

COPY api/public/ /var/www/html/
COPY api/docs/AzA_v0.7_openapi.min.yaml /var/www/html/docs/AzA_v0.7_openapi.min.yaml
