<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * レコードの新規追加、更新
 * formより

 * アプリのすべてのレコードを取得し、DBにGET同期保存する
 * スキーマの変更も反映する
 * スキーマの更新、レコードの更新の操作ログを残す
 *
 * これは1日1回程度、削除の反映のためにすべてを同期するこれを実行すべき。
 * 例えば20,000レコードある場合APIは40アクセスする。
 * 直接DBをいじった場合に強制同期したいときは、比較用キャッシュを削除する
 * make hoge
 */
class CreateAndUpdateAppTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kintone:create-and-update-app-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'アプリのすべてのレコードを取得保存';


    // KintoneApi
    private $api;

    // const
    const LIMIT = 500;
    const PRIMARY_KEY_NAME = 'レコード番号';

    // typeマッピング
    const MAP = [
        // bigint_required
        'RECORD_NUMBER' => 'bigint_required', // レコード番号 = primary key
        '__ID__' => 'bigint_required', // レコードID
        '__REVISION__' => 'bigint_required', // リビジョン
        // bigint
        'NUMBER' => 'bigint',  // stringの法が良いかもしれない
        // date
        'DATE' => 'date',
        // text
        'SINGLE_LINE_TEXT' => 'text',
        'MULTI_LINE_TEXT' => 'text',
        'CREATOR' => 'text', // 作成者
        'MODIFIER' => 'text', // 更新者
        'CALC' => 'text', //
        'FILE' => 'text', //
        'DROP_DOWN' => 'text', //
        'RADIO_BUTTON' => 'text', //
        'CHECK_BOX' => 'text', //
        'LINK' => 'text', // リンク
        'STATUS_ASSIGNEE' => 'text', // 作業者
        // json
        'CATEGORY' => 'json',
        // string
        'CREATED_TIME' => 'string', // 作成日時
        'UPDATED_TIME' => 'string', // 更新日時
        'DATETIME' => 'string', //
        'STATUS' => 'string', // ステータス
        // recoreds->get()ではとれないもの？不明なもの
        'REFERENCE_TABLE' => 'text', // 関連レコード一覧
        'GROUP' => 'text', // グループ
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->api = new \CybozuHttp\Api\KintoneApi(new \CybozuHttp\Client(config('services.kintone.login')));

        $this->updateTables();
    }

    /**
     * fields APIからアプリのテーブルスキーマを取得して変更があったらdelete table & create table
     * appが削除された場合は、そのまま放置
     *
     * アプリごと、batchフラグを見て最新0を取得して最新1との差分を見てalter table実行。
     * 最新1がなければcreate table。そもそも最新0がなければ何も実行しない。

     * フィールド名がキー。カラム名。なので、フィールド名を変更されたらカラム削除＆カラム追加になる。
     * その場合、強制AllGetやるように促したい。ってか、ここで変更検知したら絶対やったほうがいっか。 @todo;
     */
    private function updateTables()
    {
        foreach (\App\Model\Apps::all() as $app) {
            // 未実施のfields最新を検索
            $postFields = \App\Model\Fields::where([
                    'appId' => $app['appId'],
                    'batch' => false,
                ])->orderByDesc('id')->first();

            if (empty($postFields)) {
                continue;
            }

            // create table テーブル名は'app_0000000000{appId}'
            $tableName = sprintf('app_%010d', $app['appId']);

            // 以前のスキーマを取得
            $preFields = \App\Model\Fields::where([
                    'appId' => $app['appId'],
                    'batch' => true,
                ])->orderByDesc('id')->first();

            if ($preFields) {
                // テーブルスキーマ確認
                $pre = json_decode($preFields->properties, true);
                $post = json_decode($postFields->properties, true);

                // 比較
                foreach ($pre as $key => $tmp) {
                    if (! isset($post[$key])) {
                        // 削除された
                    } elseif ($post[$key] == $tmp) {
                        // 変更なし
                        unset($pre[$key]);
                        unset($post[$key]);
                    } else {
                        // 変更された
                        if (self::MAP[$post[$key]['type']] == self::MAP[$tmp['type']]) {
                            // typeの変更が不要であればスルー
                            unset($pre[$key]);
                            unset($post[$key]);
                        } else {
                            // typeの変更が必要であればテーブル作り直し
                            $this->info('drop & create table. ' . $tableName);
                            \Schema::dropIfExists($tableName);
                        }
                    }
                }

                \Log::info(json_encode(['drop column' => $pre, 'add column' => $post], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $this->info(json_encode(['drop column' => array_keys($pre), 'add column' => array_keys($post)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));



                \Schema::table(
                    $tableName,
                    function (\Illuminate\Database\Schema\Blueprint $table) use ($pre, $post) {
                        foreach (array_keys($pre) as $key) {
                            $table->dropColumn($key);
                        }
                        foreach ($post as $key => $tmp) {
                            self::hoge($table, $key, $tmp['type']);
                        }
                    });

/*
                foreach (array_keys($pre) as $key) {
                    // delete
                    \Schema::table(
                        $tableName,
                        function (\Illuminate\Database\Schema\Blueprint $table) use ($key) {
                            $table->dropColumn($key);
                        });
                }
                foreach (array_keys($post) as $key) {
                    // add
                    \Schema::table(
                        $tableName,
                        function (\Illuminate\Database\Schema\Blueprint $table) use ($key) {
                            $table->string($key);
                        });
                }
*/
            } else {
                // テーブル新規作成
                // 新規作成

                if (\Schema::hasTable($tableName)) {
                    // error
                }


                $properties = json_decode($postFields->properties, true);

                \Log::info(json_encode(['create table' => $properties], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $this->info('create table: ' . $tableName);

                \Schema::create(
                    $tableName,
                    function (\Illuminate\Database\Schema\Blueprint $table) use ($properties) {
                        // id, revision
                        $table->unsignedBigInteger('$id')->primary();
                        $table->unsignedBigInteger('$revision')->index();

                        foreach ($properties as $key => $val) {
                            self::hoge($table, $key, $val['type']);
                        }
                    });

            }

            // batchをtrueに スキップしたものも含めてすべて
            \App\Model\Fields::where([
                    'appId' => $app['appId'],
                    'batch' => false,
                ])->update([
                        'batch' => true,
                    ]);

            continue;

        }
    }

    /**
     * add schema to table
     * @params Schema $table
     * @params string $key
     * @params string $type
     */
    private static function hoge(\Illuminate\Database\Schema\Blueprint &$table, string $key, string $type)
    {
        switch (self::MAP[$type]) {
            case 'bigint_required':
                $table->unsignedBigInteger($key)
                    ->comment($type);
                break;
            case 'bigint':
                $table->bigInteger($key)->nullable()
                    ->comment($type);
                break;
            case 'date':
                $table->date($key)->nullable()
                    ->comment($type);
                break;
            case 'text':
                $table->text($key)->nullable()
                    ->comment($type);
                break;
            case 'json':
                $table->json($key)->nullable()
                    ->comment($type);
                break;
            case 'string':
                $table->string($key, 20)->nullable()
                    ->comment($type);
                break;
            default:
                dump(['error', $val]);
                exit;
        }
    }
}
