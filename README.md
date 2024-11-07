kintone-sync
============

Kintoneのデータを取得しDB(mysql)に同期、保存します。

同期時に差分だけを取り込むことでAPIリクエスト回数を節約します。<br>
mysqlへは構造化されて保存し、例えばテーブルはJSONカラムで記録されるので集計等扱いやすくなります。

## Requirement

* docker

dockerを使わない場合は

* php 8.2
* mysql 8.4

## Setup

```
make setup
make install
```

生成された`.env`にkintoneログイン情報を記入します。

```
# kintone login
KINTONE_SUBDOMAIN={Kintoneのサブドメイン}
#KINTONE_LOGIN={パスワード認証で利用する場合のユーザー名}
#KINTONE_PASSWORD={パスワード認証で利用する場合のパスワード}

KINTONE_TOKENS={APIトークンを使用する場合}

# kintone settings
KINTONE_IGNORE_APPS={同期除外アプリ}
KINTONE_INCLUDE_APPS={特定のアプリだけを同期したい場合}
```

## Usage

```
# アプリ一覧、スペースの情報、等を取得保存
make get-info

# テーブルの作成、カラム追加&削除
make create-and-update-app-tables

# アプリの追加、更新されたレコードを差分取り込み、同期
make get-apps-updated-data

# アプリで削除されたレコードを差分取り込み、同期
make get-apps-deleted-data
```

全件取得、同期しなおす場合
(認証の切り替えや権限変更時には差分取り込みが正しく認識しないためやり直す)

```
# アプリのすべてのレコードを取得、同期
make get-apps-all-data
```

ルックアップの再取得を一括実施

> config.sample/kintone.php を参考に config/kintone.php を設置しておく

```
make refresh-lookup
```
