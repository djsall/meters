#!/bin/sh
set -e

echo "🚚 Dploying application"

echo "⬇️ Laravel down"

    php artisan down || true

    echo "⬇️ Updating base code: main branch"

    git pull origin main
    git reset --hard origin/main

    echo "📦 Installing composer dependencies"

    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

    echo "🔄 Restarting Php"

    sudo -S service php8.4-fpm reload
    sudo -S service nginx reload

    echo "🗃️ Running migrations"

    php artisan migrate --force

    echo "🧹 Recreating cache"

    #Clear caches
    php artisan cache:clear

    # Clear and cache routes
    php artisan route:cache

    # Clear and cache config
    php artisan config:cache

    # Clear and cache views
    php artisan view:cache

    echo "🔄 Restarting queue"

    php artisan queue:restart

    echo "📦 Installing Npm dependencies"

    #npm ci

    echo "🏗️ Compiling assets"

    #npm run build

echo "⬆️ Rising Laravel"
php artisan up

echo "🎉 Deployed application"
