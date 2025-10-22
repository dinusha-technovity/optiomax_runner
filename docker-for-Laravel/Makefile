#!/usr/bin/make
SHELL = /bin/sh
UID := $(shell id -u)
GID := $(shell id -g)
export UID
export GID

up-f:
	docker compose -f docker-compose-dev.yml up --build

up:
	docker compose -f docker-compose-dev.yml up -d --build

down:
	docker compose -f docker-compose-dev.yml down

shell:
	docker exec -u www-data -it test /bin/sh

logs:
	docker compose -f docker-compose-dev.yml logs -f

logs-nginx:
	docker logs test-nginx -f

logs-app:
	docker logs test -f

restart:
	docker compose -f docker-compose-dev.yml restart

app:
	[ -d $(APP_VOLUMES_SRC) ] || mkdir src
	docker compose -f docker-compose-dev.yml build \
	&& docker compose -f docker-compose-dev.yml run testapi \
	composer create laravel/laravel .

key:
	docker compose -f docker-compose-dev.yml run testapi \
	php artisan key:generate --ansi

migrate:
	docker compose -f docker-compose-dev.yml exec testapi \
	php artisan migrate

db-setup:
	@echo "Make sure your local PostgreSQL is running and the database 'newapp' exists"
	@echo "You can create it with: sudo -u postgres createdb newapp"

