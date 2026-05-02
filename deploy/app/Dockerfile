FROM php:8.3-apache

WORKDIR /var/www/html

# Dependencies for Composer installs.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends unzip ca-certificates \
	&& rm -rf /var/lib/apt/lists/*

# Install Composer.
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
	&& php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
	&& rm composer-setup.php

RUN a2enmod headers rewrite

COPY deploy/app/security.conf /etc/apache2/conf-enabled/zzz-sowwwl-security.conf
COPY deploy/app/upload-limits.ini /usr/local/etc/php/conf.d/zzz-sowwwl-upload.ini
COPY deploy/app/entrypoint.sh /usr/local/bin/sowwwl-entrypoint

# Install PHP dependencies (PHPMailer) for magic-link email sending.
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

COPY .htaccess ./
COPY index.php land.php config.php styles.css main.js manifest.json site-sw.js favicon.svg aza.php signal.php signal_item.php signal_post.php logout.php echo.php 0wlslw0.php 0wlslw0_voice.php str3m.php map.php map_points.php sh0re.php n.php ./
COPY aza_portals ./aza_portals
COPY lib ./lib
COPY icons ./icons
COPY sites ./sites

RUN chmod +x /usr/local/bin/sowwwl-entrypoint

ENTRYPOINT ["sowwwl-entrypoint"]
CMD ["apache2-foreground"]
