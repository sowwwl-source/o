#!/bin/sh
set -eu

mkdir -p \
	/var/www/runtime/lands \
	/var/www/runtime/rate-limit \
	/var/www/runtime/signals/items \
	/var/www/runtime/plasma \
	/var/www/runtime/aza/imports \
	/var/www/runtime/aza/files/thumbs \
	/var/www/html/storage

if [ -d /var/www/html/storage/signals ] && [ ! -L /var/www/html/storage/signals ]; then
	cp -a /var/www/html/storage/signals/. /var/www/runtime/signals/ 2>/dev/null || true
	rm -rf /var/www/html/storage/signals
fi

if [ -d /var/www/html/storage/aza ] && [ ! -L /var/www/html/storage/aza ]; then
	cp -a /var/www/html/storage/aza/. /var/www/runtime/aza/ 2>/dev/null || true
	rm -rf /var/www/html/storage/aza
fi

ln -sfn /var/www/runtime/signals /var/www/html/storage/signals
ln -sfn /var/www/runtime/aza /var/www/html/storage/aza
chown -R www-data:www-data /var/www/runtime

exec "$@"
