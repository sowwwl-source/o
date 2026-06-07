FROM php:8.3-apache

WORKDIR /var/www/html

# Dependencies for Composer installs.
RUN apt-get update \
	&& apt-get install -y --no-install-recommends unzip ca-certificates libsqlite3-dev pkg-config \
	&& docker-php-ext-install pdo_mysql pdo_sqlite \
	&& rm -rf /var/lib/apt/lists/*

# Install Composer.
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
	&& php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
	&& rm composer-setup.php

RUN a2enmod headers rewrite \
	&& a2dismod -f alias autoindex

COPY deploy/app/security.conf /etc/apache2/conf-enabled/zzz-sowwwl-security.conf
COPY deploy/app/upload-limits.ini /usr/local/etc/php/conf.d/zzz-sowwwl-upload.ini
COPY deploy/app/entrypoint.sh /usr/local/bin/sowwwl-entrypoint

# Install PHP dependencies (PHPMailer) for magic-link email sending.
COPY composer.json ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

COPY .htaccess ./
COPY index.php 404.php robots.php sitemap.php land.php island.php camera.php camera_ai.php sceptre.php sceptre_state.php sceptre_constellation.php config.php styles.css main.js public-shell.js manifest.php manifest.json site-sw.js favicon.svg apple-touch-icon.png aza.php aza_asset.php rejoindre.php signal.php signal_item.php signal_post.php signal_live.php logout.php echo.php api_voice.php 0wlslw0.php 0wlslw0_voice.php str3m.php map.php map_points.php sh0re.php n.php n0de.php qr.js ingest_sensor.php ingest_camera_ai.php ingest_sceptre.php ingest_membrane.php plasma_recent.php ./
COPY aza_portals ./aza_portals
COPY lib ./lib
COPY scripts/check_signal_validation.php scripts/check_0wlslw0_agent.php scripts/check_spatial_surface.php scripts/check_media_readers.php scripts/plasma_backfill_from_jsonl.php ./scripts/
COPY icons ./icons

RUN chmod +x /usr/local/bin/sowwwl-entrypoint

ENTRYPOINT ["sowwwl-entrypoint"]
CMD ["apache2-foreground"]
