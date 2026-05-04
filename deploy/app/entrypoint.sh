#!/bin/sh
set -eu

mkdir -p /var/www/runtime/lands /var/www/runtime/rate-limit /var/www/runtime/plasma
chown -R www-data:www-data /var/www/runtime

exec "$@"
