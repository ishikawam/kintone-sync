kintone-sync
============

Kintoneのデータを取得しDB(mysql)に同期、保存します。

## Requirement

* git
* docker

## Setup

```
make setup
make install
```

生成された`.env`にkintoneログイン情報を記入

## Usage

```
# アプリ一覧、スペースの情報、等を取得保存
make get-info

# テーブルの作成、カラム追加&削除
make create-and-update-app-tables

# アプリの更新されたのレコードを取得保存
make get-apps-updated-data

# アプリの削除されたのレコードを取得保存
make get-apps-deleted-data

```

全件取得時

```
# アプリのすべてのレコードを取得保存
make get-apps-all-data
```

ルックアップの再取得を一括実施

> config.sample/kintone.php を参考に config/kintone.php を設置しておく

```
make refresh-lookup
```
