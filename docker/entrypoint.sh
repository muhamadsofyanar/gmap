#!/bin/sh
set -eu
mkdir -p /var/www/html/storage/sessions /var/www/html/storage/browser
chown -R www-data:www-data /var/www/html/storage
chmod 700 /var/www/html/storage/sessions
php /var/www/html/scripts/check_runtime.php
php /var/www/html/scripts/install.php
exec docker-php-entrypoint "$@"
