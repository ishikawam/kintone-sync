# kintone-sync

all: setup

setup:
	-cp -n .env.sample/.env_local .env
	-cp -n config.sample/kintone.php config/kintone.php
	docker compose pull
	docker compose build
	docker compose run --rm php composer install
	docker compose run --rm php php artisan key:generate

install:
	docker compose run --rm php composer install
	docker compose run --rm php php artisan clear-compiled

migrate:
	docker compose exec php bash -c "php artisan migrate"

migrate-rollback:
	docker compose exec php bash -c "php artisan migrate:rollback"

seed:
	docker compose exec php bash -c "php artisan migrate:refresh --seed"

up:
	docker compose up

down:
	docker compose down --remove-orphans

log:
	tail -f ./storage/logs/*

start:
	docker compose start

stop:
	docker compose stop

restart:
	docker compose restart

ssh:
	docker compose exec php bash

clear:
	docker compose run --rm php bash -c "composer dump-autoload --optimize"
	docker compose run --rm php bash -c "php artisan clear-compiled ; php artisan config:clear"

fix:
	docker compose run --rm php ./vendor/bin/pint

analyse:
	docker compose run --rm php ./vendor/bin/phpstan analyse

#######################################
# kintone-sync commands

get-info:
	docker compose exec php php artisan kintone:get-info

create-and-update-app-tables:
	docker compose exec php php artisan kintone:create-and-update-app-tables

get-apps-all-data:
	docker compose exec php php artisan kintone:get-apps-all-data

get-apps-updated-data:
	docker compose exec php php artisan kintone:get-apps-updated-data

get-apps-deleted-data:
	docker compose exec php php artisan kintone:get-apps-deleted-data

refresh-lookup:
	docker compose exec php php artisan kintone:refresh-lookup

# バックアップを実施
run: get-info create-and-update-app-tables get-apps-updated-data get-apps-deleted-data

destroy:
	@echo "remove mysql data. Are you sure? " && read ans && [ $$ans == yes ]
	docker compose down --remove-orphans
	rm -r storage/mysql/data
