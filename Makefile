# kintone-sync

.DEFAULT_GOAL := help

.PHONY: help setup install migrate migrate-rollback up down log start stop restart ssh clear fix analyse \
	get-info create-and-update-app-tables get-apps-all-data get-apps-updated-data get-apps-deleted-data \
	refresh-lookup run destroy

help: ## このヘルプメッセージを表示
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

setup: ## 初期セットアップ
	-cp -n .env.sample/.env_local .env
	-cp -n config.sample/kintone.php config/kintone.php
	docker compose pull
	docker compose build
	docker compose run --rm php composer install
	docker compose run --rm php php artisan key:generate

install: ## 依存関係のインストール
	docker compose run --rm php composer install
	docker compose run --rm php php artisan clear-compiled

migrate: ## マイグレーション実行
	docker compose exec php bash -c "php artisan migrate"

migrate-rollback: ## マイグレーションのロールバック
	docker compose exec php bash -c "php artisan migrate:rollback"

up: ## Dockerコンテナ起動
	docker compose up

down: ## Dockerコンテナ停止
	docker compose down --remove-orphans

log: ## ログをtail表示
	tail -f ./storage/logs/*

start: ## Dockerコンテナをstart
	docker compose start

stop: ## Dockerコンテナをstop
	docker compose stop

restart: ## Dockerコンテナを再起動
	docker compose restart

ssh: ## phpコンテナのシェルに接続
	docker compose exec php bash

clear: ## キャッシュクリア
	docker compose run --rm php bash -c "composer dump-autoload --optimize"
	docker compose run --rm php bash -c "php artisan clear-compiled ; php artisan config:clear"

fix: ## コードフォーマット
	docker compose run --rm php ./vendor/bin/pint

analyse: ## PHPStan静的解析
	docker compose run --rm php ./vendor/bin/phpstan analyse

#######################################
# kintone-sync commands

get-info: ## アプリ一覧、スペースの情報を取得保存
	docker compose exec php php artisan kintone:get-info

create-and-update-app-tables: ## テーブルの作成、カラム追加と削除
	docker compose exec php php artisan kintone:create-and-update-app-tables

get-apps-all-data: ## アプリのすべてのレコードを取得、同期
	docker compose exec php php artisan kintone:get-apps-all-data

get-apps-updated-data: ## 追加、更新されたレコードを差分同期
	docker compose exec php php artisan kintone:get-apps-updated-data

get-apps-deleted-data: ## 削除されたレコードを差分同期
	docker compose exec php php artisan kintone:get-apps-deleted-data

refresh-lookup: ## ルックアップの再取得を一括実施
	docker compose exec php php artisan kintone:refresh-lookup

# バックアップを実施
run: get-info create-and-update-app-tables get-apps-updated-data get-apps-deleted-data ## バックアップを実施

destroy: ## mysqlデータを削除しコンテナを破棄
	@echo "remove mysql data. Are you sure? " && read ans && [ $$ans == yes ]
	docker compose down --remove-orphans
	rm -r storage/mysql/data
