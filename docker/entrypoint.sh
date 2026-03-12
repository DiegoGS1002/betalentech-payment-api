#!/bin/bash
set -e

cd /app

echo "Waiting for MySQL to be ready..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if php -r "try { new PDO('mysql:host=mysql;port=3306;dbname=betalentech', 'laravel', 'laravel'); echo 'connected'; exit(0); } catch(Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "MySQL is ready!"
        break
    fi
    attempt=$((attempt + 1))
    echo "Attempt $attempt/$max_attempts - MySQL not ready yet..."
    sleep 3
done

if [ $attempt -eq $max_attempts ]; then
    echo "ERROR: Could not connect to MySQL after $max_attempts attempts"
    exit 1
fi

# Install PHP dependencies
if [ -f composer.json ]; then
    if [ -f composer.lock ]; then
        composer install --no-interaction --prefer-dist --optimize-autoloader
    else
        composer install --no-interaction --optimize-autoloader
    fi
fi

# Generate app key if not set
php artisan key:generate --force --no-interaction 2>/dev/null || true

# Publish Sanctum migrations (only if not already published)
if ! ls database/migrations/*_create_personal_access_tokens_table.php 1>/dev/null 2>&1; then
    php artisan vendor:publish --tag=sanctum-migrations --no-interaction 2>/dev/null || true
fi

# Run migrations
php artisan migrate --force

# Seed database
php artisan db:seed --force 2>/dev/null || true

echo "Laravel setup complete!"
