#!/bin/bash

echo "🔧 Permanent Laravel Storage Permissions Fix"
echo "============================================="

CONTAINER_NAME="optiomax_runner"

echo "🛑 Stopping all supervisor processes..."
docker exec -t $CONTAINER_NAME supervisorctl stop all

echo "🗑️  Removing existing log files with wrong permissions..."
docker exec -t $CONTAINER_NAME sh -c "rm -f /var/www/html/storage/logs/*.log"

echo "📁 Creating directory structure..."
docker exec -t $CONTAINER_NAME sh -c "mkdir -p /var/www/html/storage/logs /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/bootstrap/cache"

echo "👤 Setting ownership to www-data..."
docker exec -t $CONTAINER_NAME sh -c "chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache"

echo "🔐 Setting permissions..."
docker exec -t $CONTAINER_NAME sh -c "chmod -R 777 /var/www/html/storage"
docker exec -t $CONTAINER_NAME sh -c "chmod -R 775 /var/www/html/bootstrap/cache"

echo "📝 Creating Laravel log file with correct permissions..."
docker exec -t $CONTAINER_NAME sh -c "touch /var/www/html/storage/logs/laravel.log"
docker exec -t $CONTAINER_NAME sh -c "chown www-data:www-data /var/www/html/storage/logs/laravel.log"
docker exec -t $CONTAINER_NAME sh -c "chmod 666 /var/www/html/storage/logs/laravel.log"

echo "✅ Verifying permissions..."
docker exec -t $CONTAINER_NAME sh -c "ls -la /var/www/html/storage/logs/"

echo "🚀 Starting all supervisor processes..."
docker exec -t $CONTAINER_NAME supervisorctl start all

echo "📊 Checking supervisor status..."
docker exec -t $CONTAINER_NAME supervisorctl status

echo "🎉 Permissions fix completed!"
echo ""
echo "To test, run:"
echo "docker exec -t $CONTAINER_NAME php /var/www/html/artisan --version"
