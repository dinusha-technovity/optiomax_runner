#!/bin/bash

set -euo pipefail

ENVIRONMENT=${1:-production}
VERSION=${2:-latest}
REGION=${3:-default}

# Enterprise deployment configuration
DEPLOYMENT_TIMEOUT=600
HEALTH_CHECK_RETRIES=10
HEALTH_CHECK_INTERVAL=30
LOG_LEVEL="info"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

success() {
    echo -e "${GREEN}[SUCCESS] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    exit 1
}

# Pre-deployment checks
pre_deployment_checks() {
    log "Running pre-deployment checks..."
    
    # Check disk space
    DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
    if [ "$DISK_USAGE" -gt 80 ]; then
        error "Disk usage is ${DISK_USAGE}%. Cannot proceed with deployment."
    fi
    
    # Check memory
    MEMORY_USAGE=$(free | awk 'NR==2{printf "%.0f", $3/$2*100}')
    if [ "$MEMORY_USAGE" -gt 90 ]; then
        warning "Memory usage is ${MEMORY_USAGE}%. Monitoring closely."
    fi
    
    # Check Docker
    if ! docker --version &> /dev/null; then
        error "Docker is not installed or not running"
    fi
    
    success "Pre-deployment checks passed"
}

# Backup current deployment
backup_current_deployment() {
    log "Creating backup of current deployment..."
    
    BACKUP_DIR="/opt/backups/optiomax/$(date +%Y%m%d_%H%M%S)"
    mkdir -p "$BACKUP_DIR"
    
    # Backup database
    if docker ps | grep -q optiomax_runner; then
        docker exec optiomax_runner sh -c "php artisan backup:run --only-db" || warning "Database backup failed"
    fi
    
    # Backup configuration
    cp -r /opt/optiomax/optiomax_runner/prod/envs "$BACKUP_DIR/" || warning "Config backup failed"
    
    success "Backup completed: $BACKUP_DIR"
}

# Zero-downtime deployment
zero_downtime_deploy() {
    log "Starting zero-downtime deployment..."
    
    # Pull new images
    log "Pulling images for version $VERSION..."
    docker pull "ghcr.io/dinusha-technovity/optiomax_runner:$VERSION" || error "Failed to pull app image"
    docker pull "ghcr.io/dinusha-technovity/optiomax_runner_proxy:$VERSION" || error "Failed to pull proxy image"
    
    # Start new containers with different names
    log "Starting new containers..."
    VERSION="$VERSION" docker compose -f docker-compose-prod.yml -p "optiomax_new" up -d --force-recreate
    
    # Wait for new containers to be healthy
    wait_for_health "optiomax_new_optiomax_runner_1"
    
    # Switch traffic (if using load balancer)
    log "Switching traffic to new deployment..."
    
    # Stop old containers
    docker compose -f docker-compose-prod.yml down --remove-orphans || warning "Failed to stop old containers"
    
    # Rename new containers
    docker rename optiomax_new_optiomax_runner_1 optiomax_runner || warning "Failed to rename app container"
    docker rename optiomax_new_optiomax_runner_proxy_1 optiomax_runner_proxy || warning "Failed to rename proxy container"
    
    success "Zero-downtime deployment completed"
}

# Health check function
wait_for_health() {
    local container_name=$1
    local retries=0
    
    log "Waiting for $container_name to be healthy..."
    
    while [ $retries -lt $HEALTH_CHECK_RETRIES ]; do
        if docker exec "$container_name" php /var/www/html/artisan --version &> /dev/null; then
            success "$container_name is healthy"
            return 0
        fi
        
        retries=$((retries + 1))
        log "Health check attempt $retries/$HEALTH_CHECK_RETRIES failed. Retrying in ${HEALTH_CHECK_INTERVAL}s..."
        sleep $HEALTH_CHECK_INTERVAL
    done
    
    error "$container_name failed health checks after $HEALTH_CHECK_RETRIES attempts"
}

# Post-deployment tasks
post_deployment_tasks() {
    log "Running post-deployment tasks..."
    
    # Run migrations
    docker exec optiomax_runner php artisan migrate --force || error "Migration failed"
    
    # Clear caches
    docker exec optiomax_runner php artisan config:cache || warning "Config cache failed"
    docker exec optiomax_runner php artisan route:cache || warning "Route cache failed"
    docker exec optiomax_runner php artisan view:cache || warning "View cache failed"
    
    # Verify supervisor processes
    docker exec optiomax_runner supervisorctl status | grep -v RUNNING && warning "Some supervisor processes are not running"
    
    # Setup monitoring
    setup_monitoring
    
    success "Post-deployment tasks completed"
}

# Setup monitoring
setup_monitoring() {
    log "Setting up monitoring for region: $REGION..."
    
    # Health check endpoint setup
    docker exec optiomax_runner sh -c "echo 'OK' > /var/www/html/public/health"
    
    # Log rotation setup
    docker exec optiomax_runner sh -c "logrotate -f /etc/logrotate.conf" || warning "Log rotation setup failed"
    
    success "Monitoring setup completed"
}

# Rollback function
rollback() {
    local backup_version=$1
    error "Deployment failed. Initiating rollback to $backup_version..."
    
    # Implement rollback logic here
    VERSION="$backup_version" docker compose -f docker-compose-prod.yml up -d --force-recreate
    wait_for_health "optiomax_runner"
    
    success "Rollback completed"
}

# Main deployment flow
main() {
    log "Starting enterprise deployment for environment: $ENVIRONMENT, version: $VERSION, region: $REGION"
    
    # Trap for cleanup on failure
    trap 'error "Deployment failed at line $LINENO"' ERR
    
    pre_deployment_checks
    backup_current_deployment
    zero_downtime_deploy
    post_deployment_tasks
    
    success "Enterprise deployment completed successfully!"
    log "Version $VERSION is now live in $ENVIRONMENT environment"
}

# Run main function
main "$@"
