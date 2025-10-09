# Enterprise-grade Laravel deployment commands

migrate:
	docker exec -t optiomax_runner sh -c "php artisan migrate --force"

migrate-rollback:
	docker exec -t optiomax_runner sh -c "php artisan migrate:rollback"

migrate-status:
	docker exec -t optiomax_runner sh -c "php artisan migrate:status"

passport-install:
	docker exec -t optiomax_runner sh -c "php artisan passport:install --force"

tenant-migrate:
	docker exec -t optiomax_runner sh -c "php artisan tenantss:migrates:all tenant"

cache-clear:
	docker exec -t optiomax_runner sh -c "php artisan cache:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache"

full-migration: migrate passport-install tenant-migrate cache-clear
	@echo "✅ Full migration process completed"

health-check:
	docker exec -t optiomax_runner php /var/www/html/artisan --version
	docker exec -t optiomax_runner supervisorctl status

logs:
	docker exec -t optiomax_runner tail -f /var/www/html/storage/logs/laravel.log

supervisor-status:
	docker exec -t optiomax_runner supervisorctl status

supervisor-restart:
	docker exec -t optiomax_runner supervisorctl restart all

.PHONY: migrate migrate-rollback migrate-status passport-install tenant-migrate cache-clear full-migration health-check logs supervisor-status supervisor-restart