#!/bin/bash

echo "🔍 Supervisor Diagnostic Script"
echo "==============================="

CONTAINER_NAME="optiomax_runner"

echo "📊 Current supervisor status:"
docker exec -t $CONTAINER_NAME supervisorctl status

echo ""
echo "🔍 Checking Laravel artisan commands:"
docker exec -t $CONTAINER_NAME php /var/www/html/artisan list | grep -E "(listen|queue|notify)"

echo ""
echo "📂 Checking log file permissions:"
docker exec -t $CONTAINER_NAME ls -la /var/www/html/storage/logs/

echo ""
echo "👤 Checking process ownership:"
docker exec -t $CONTAINER_NAME ps aux | grep -E "(php|artisan)"

echo ""
echo "🔧 Checking supervisor configuration:"
docker exec -t $CONTAINER_NAME cat /etc/supervisor/conf.d/listen-registration.conf

echo ""
echo "📝 Checking recent supervisor logs:"
docker exec -t $CONTAINER_NAME supervisorctl tail supervisord

echo ""
echo "🔄 Attempting to restart failed processes:"
docker exec -t $CONTAINER_NAME supervisorctl restart all

echo ""
echo "📊 Final supervisor status:"
docker exec -t $CONTAINER_NAME supervisorctl status
