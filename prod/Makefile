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

fix-permissions:
	./scripts/fix-permissions-permanent.sh

health-check:
	./scripts/health-check.sh

.PHONY: migrate migrate-rollback passport-install tenant-migrate cache-clear full-migration supervisor-status fix-permissions health-check