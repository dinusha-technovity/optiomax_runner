#!/bin/bash

echo "Performing post-deployment health checks..."

# Check if container is running
if ! docker ps | grep -q optiomax_runner; then
    echo "ERROR: Container optiomax_runner is not running"
    exit 1
fi

# Check supervisor status
echo "Checking supervisor processes..."
docker exec -t optiomax_runner supervisorctl status

# Check if critical processes are running
CRITICAL_PROCESSES=("tenant-registration-worker" "queue-worker" "csv-import-worker" "scheduler")

for process in "${CRITICAL_PROCESSES[@]}"; do
    if docker exec -t optiomax_runner supervisorctl status | grep -q "$process.*RUNNING"; then
        echo "✅ $process is running"
    else
        echo "⚠️  $process may have issues"
    fi
done

# Check Laravel application
if docker exec -t optiomax_runner php /var/www/html/artisan --version; then
    echo "✅ Laravel application is accessible"
else
    echo "❌ Laravel application has issues"
    exit 1
fi

# Check queue connections
echo "Checking Redis connection for queues..."
if docker exec -t optiomax_runner php /var/www/html/artisan queue:monitor &> /dev/null; then
    echo "✅ Redis queue connection is working"
else
    echo "⚠️  Redis queue connection may have issues"
fi

echo "Health check completed"
