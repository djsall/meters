#!/bin/sh

cd /var/www/html

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev -q

# Clear and rebuild caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart Queues (If using Horizon or standard workers)
php artisan queue:restart

# Run migrations (Optional: use --force for production)
php artisan migrate --force

exec "$@"
