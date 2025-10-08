#!/bin/bash

echo "Fixing Laravel storage permissions..."

# Stop supervisor processes
docker exec -t optiomax_runner supervisorctl stop all

# Fix permissions with more comprehensive approach
docker exec -t optiomax_runner sh -c "mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache"
docker exec -t optiomax_runner sh -c "chown -R www-data:www-data storage bootstrap/cache"
docker exec -t optiomax_runner sh -c "chmod -R 777 storage/logs"
docker exec -t optiomax_runner sh -c "chmod -R 775 storage bootstrap/cache"

# Specific fixes for OAuth keys
docker exec -t optiomax_runner sh -c "touch storage/oauth-public.key storage/oauth-private.key"
docker exec -t optiomax_runner sh -c "chown www-data:www-data storage/oauth-*.key"
docker exec -t optiomax_runner sh -c "chmod 666 storage/oauth-*.key"

# Ensure storage root is writable
docker exec -t optiomax_runner sh -c "chmod 777 storage"

# Restart supervisor processes
docker exec -t optiomax_runner supervisorctl start all

echo "Permissions fixed and services restarted."
echo "OAuth key permissions specifically addressed."
