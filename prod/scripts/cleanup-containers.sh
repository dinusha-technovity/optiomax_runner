#!/bin/bash

echo "🧹 Container Cleanup Script"
echo "==========================="

CONTAINER_NAMES=("optiomax_runner" "optiomax_runner_proxy")

echo "Stopping and removing containers..."
for container in "${CONTAINER_NAMES[@]}"; do
    if docker ps -q -f name=$container | grep -q .; then
        echo "Stopping $container..."
        docker stop $container || true
    fi
    
    if docker ps -aq -f name=$container | grep -q .; then
        echo "Removing $container..."
        docker rm $container || true
    fi
done

echo "Cleaning up orphaned containers..."
docker container prune -f

echo "Cleaning up unused images..."
docker image prune -f

echo "Cleanup completed!"
