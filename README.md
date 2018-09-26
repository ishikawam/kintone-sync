kintone-sync
===================

dockerの動作環境が必要です。

## setup

```
make setup
make install
```

生成された`.env`にkintoneログイン情報を記入

## running

```
# アプリ一覧、スペースの情報、等を取得保存
make get-info

# テーブルの作成、カラム追加&削除
make create-and-update-app-tables

# プリの更新されたのレコードを取得保存
make get-apps-updated-data

# アプリの削除されたのレコードを取得保存
make get-apps-deleted-data

```

全件取得時

```
# アプリのすべてのレコードを取得保存
make get-apps-all-data
```
