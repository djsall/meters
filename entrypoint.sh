#!/bin/sh
set -e

echo "Current user: $(whoami)"

cd /var/www/html

# Everyone needs the vendor folder to exist before starting
# If it's missing, we run a quick install
if [ ! -d "vendor" ]; then
    composer install --no-interaction --optimize-autoloader --no-dev
fi

# Only the APP container should handle migrations and caching
# We detect this by checking if the command is 'php-fpm'
if [ "$1" = "php-fpm" ]; then
    php artisan migrate --force
    php artisan config:cache
    php artisan route:cache
fi

chown -R www-data:www-data /var/www/html

exec "$@"
