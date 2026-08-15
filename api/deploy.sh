#!/bin/sh

# Exit the script as soon as a command fails
set -e

# Clear cached Laravel state first so new environment values are used for DB access.
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Create mysql databases if none exists
php artisan mysql:createdb

# Run migrations
php artisan migrate --force

# Run migrations for sandbox too
php artisan sandbox:migrate --force

# Seed database
php artisan fleetbase:seed

# Create permissions, policies, and roles
php artisan fleetbase:create-permissions

# Restart queue
php artisan queue:restart

# Sync scheduler
php artisan schedule-monitor:sync

# Optimize
php artisan config:cache
php artisan route:cache

# Initialize registry
php artisan registry:init

# Notify open install pages that setup has completed
php artisan fleetbase:notify-installed || true

# Restart octane
# php artisan octane:reload
