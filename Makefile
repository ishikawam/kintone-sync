# meore

# mac: homebrew, git, docker, php 7.1

NAME=kintone-sync

setup:
# todo awscli, amazon-ecs-cliは不要だし、すでに入っている場合にエラーになる
	-brew install git jq awscli amazon-ecs-cli
	-cp -n .env.sample/.env_local .env
	-cp -n config.sample/kintone.php config/kintone.php
	php artisan key:generate

install:
	git submodule update --init
	npm install
	npm run dev
	make up
	docker exec -it $(NAME)_php_1 bash -c "php composer.phar install"
	docker exec -it $(NAME)_php_1 bash -c "php artisan clear-compiled"
	make migrate

migrate:
	docker exec -it $(NAME)_php_1 bash -c "php artisan migrate"

migrate-rollback:
	docker exec -it $(NAME)_php_1 bash -c "php artisan migrate:rollback"

seed:
	docker exec -it $(NAME)_php_1 bash -c "php artisan migrate:refresh --seed"

up:
	docker-compose -p $(NAME) up -d --build

down:
	docker-compose -p $(NAME) down

logs:
	docker-compose -p $(NAME) logs -f

log:
	tail -f ./storage/logs/*

start:
	docker-compose -p $(NAME) start

stop:
	docker-compose -p $(NAME) stop

restart:
	docker-compose -p $(NAME) restart

open:
	open http://localhost:10082

ssh:
	docker exec -it $(NAME)_php_1 bash

clear:
	docker exec -it $(NAME)_php_1 bash -c "php composer.phar dump-autoload --optimize"
	docker exec -it $(NAME)_php_1 bash -c "php artisan clear-compiled ; php artisan config:clear"

#######################################
# kintone commands

get-info:
	docker exec -it $(NAME)_php_1 bash -c "php artisan kintone:get-info"

create-and-update-app-tables:
	docker exec -it $(NAME)_php_1 bash -c "php artisan kintone:create-and-update-app-tables"

get-apps-all-data:
	docker exec -it $(NAME)_php_1 bash -c "php artisan kintone:get-apps-all-data"

get-apps-updated-data:
	docker exec -it $(NAME)_php_1 bash -c "php artisan kintone:get-apps-updated-data"

get-apps-deleted-data:
	docker exec -it $(NAME)_php_1 bash -c "php artisan kintone:get-apps-deleted-data"
