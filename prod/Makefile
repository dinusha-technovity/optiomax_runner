migrate:
	docker exec -t optiomax_runner sh -c "php artisan migrate --force"

migrate-rollback:
	docker exec -t optiomax_runner sh -c "php artisan migrate:rollback"

passport-install:
	docker exec -t optiomax_runner sh -c "php artisan passport:install --force"

tenant-migrate:
	docker exec -t optiomax_runner sh -c "php artisan tenantss:migrates:all tenant"

cache-clear:
	docker exec -t optiomax_runner sh -c "php artisan config:cache"

full-migration: migrate passport-install tenant-migrate cache-clear
	@echo "✅ Full migration process completed"

supervisor-status:
	docker exec -t optiomax_runner supervisorctl status

csv-worker-status:
	docker exec -t optiomax_runner supervisorctl status csv-import-worker

csv-worker-restart:
	docker exec -t optiomax_runner supervisorctl restart csv-import-worker

csv-worker-stop:
	docker exec -t optiomax_runner supervisorctl stop csv-import-worker

# Emergency: stop only CSV workers if needed
csv-emergency-stop:
	docker exec -t optiomax_runner supervisorctl stop csv-import-worker:*

# Check all workers (including existing ones)
all-workers-status:
	docker exec -t optiomax_runner supervisorctl status | grep -E "(queue-worker|csv-import-worker)"

fix-permissions:
	./scripts/fix-permissions-permanent.sh

health-check:
	./scripts/health-check.sh

.PHONY: migrate migrate-rollback passport-install tenant-migrate cache-clear full-migration supervisor-status csv-worker-status csv-worker-restart csv-worker-stop csv-emergency-stop all-workers-status fix-permissions health-check