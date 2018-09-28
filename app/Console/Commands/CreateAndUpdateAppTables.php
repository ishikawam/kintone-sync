<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * テーブルの作成、カラム追加&削除
 * カラムの更新時はdrop column & add columnで作り直す
 * Fieldsより
 *
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
    protected $description = 'テーブルの作成、カラム追加&削除';


    // KintoneApi
    private $api;

    // GetAppsData強制実行フラグ
    private $updatedApps = [];

    // const

    /**
     * typeマッピング
     * NUMBERはじめ数値は小数点あったりなかったり空白だったりがあり得るのでint系は使用できない
     * @see https://developer.cybozu.io/hc/ja/articles/202166330
     * @todo; 網羅していない
     */
    const TYPE_MAP = [
        // bigint_required
        'RECORD_NUMBER' => 'bigint_required', // レコード番号 = primary key
        '__ID__' => 'bigint_required', // レコードID
        '__REVISION__' => 'bigint_required', // リビジョン
        // date
        'DATE' => 'date',
        // text
        'SINGLE_LINE_TEXT' => 'text',
        'MULTI_LINE_TEXT' => 'text',
        'CREATOR' => 'text', // 作成者
        'MODIFIER' => 'text', // 更新者
        'FILE' => 'text', //
        'DROP_DOWN' => 'text', //
        'RADIO_BUTTON' => 'text', //
        'CHECK_BOX' => 'text', //
        'LINK' => 'text', // リンク
        'STATUS_ASSIGNEE' => 'text', // 作業者
        // json
        'CATEGORY' => 'json',
        // string
        'NUMBER' => 'string',  // 小数点あったりなかったり、空白、もありえるので型としてはstring。
        'CREATED_TIME' => 'string', // 作成日時
        'UPDATED_TIME' => 'string', // 更新日時
        'DATETIME' => 'string', // 日時
        'STATUS' => 'string', // ステータス
        'CALC' => 'string', // 計算
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
        $this->question('start. ' . __CLASS__);

        $this->api = new \CybozuHttp\Api\KintoneApi(new \CybozuHttp\Client(config('services.kintone.login')));

        $this->updateTables();

        $updatedApps = array_unique($this->updatedApps);
        foreach ($this->updatedApps as $updatedAppId) {
            $this->comment('...waiting 2 seconds... (app:' . $updatedAppId);
            sleep(2);
            $this->call('kintone:get-apps-all-data', [
                    'appId' => $updatedAppId,
                ]);
        }

        $this->question('end. ' . __CLASS__);
    }

    /**
     * fields APIからアプリのフィールド(app_テーブルのスキーマに対応)を取得し、
     * 追加フィールドはadd column。削除されたフィールドはdrop column
     * 変更されたフィールドはdrop column & add columnで作り直す
     * アプリが削除された場合は、そのまま放置
     *
     * アプリごと、batchフラグを見て最新false(未マイグレート)を取得して最新true(マイグレート済み)との差分を見て判定する。
     * 最新trueがなければcreate table。そもそも最新falseがなければ何も実行しない。
     * 最新true, 最新falseの両方があればその差分をマイグレートする。
     *
     * フィールド名がキー＝カラム名。なので、フィールド名を変更されたらカラム削除＆カラム追加になる。
     * 上記更新があったら即GetAppAllDataを実施する
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

            $this->info('updated table: ' . $tableName);

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
                foreach ($pre as $key => $val) {
                    if (! isset($post[$key])) {
                        // 削除された
                    } elseif ($post[$key] == $val) {
                        // 変更なし
                        unset($pre[$key]);
                        unset($post[$key]);
                    } else {
                        // 変更された
                        if (self::TYPE_MAP[$post[$key]['type']] == self::TYPE_MAP[$val['type']]) {
                            // typeの変更が不要であればスルー
                            unset($pre[$key]);
                            unset($post[$key]);
                        } else {
                            // typeの変更が必要であればカラム作り直し
                        }
                    }
                }

                \Log::info(['drop column' => $pre, 'add column' => $post]);
                $this->warn(json_encode(['drop column' => array_keys($pre), 'add column' => array_keys($post)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

                // 作り直しがある場合のためにカラム削除とカラム追加は別々に行う
                if (! empty($pre)) {
                    $this->updatedApps[] = $app['appId'];
                    \Schema::table(
                        $tableName,
                        function (\Illuminate\Database\Schema\Blueprint $table) use ($pre) {
                            foreach (array_keys($pre) as $key) {
                                $table->dropColumn($key);
                            }
                        });
                }
                if (! empty($post)) {
                    $this->updatedApps[] = $app['appId'];
                    \Schema::table(
                        $tableName,
                        function (\Illuminate\Database\Schema\Blueprint $table) use ($post) {
                            foreach ($post as $key => $val) {
                                self::addColumn($table, $key, $val['type']);
                            }
                        });
                }

            } else {
                // テーブル新規作成
                if (\Schema::hasTable($tableName)) {
                    throw new \Exception('テーブル ' . $tableName . ' が既にあります。テーブルを削除するかfieldsのbatchフラグを調整してください。');
                }

                $this->updatedApps[] = $app['appId'];

                $post = json_decode($postFields->properties, true);

                \Log::info(['create table' => $post]);
                $this->warn('create table: ' . $tableName);

                \Schema::create(
                    $tableName,
                    function (\Illuminate\Database\Schema\Blueprint $table) use ($post) {
                        // id, revision
                        $table->unsignedBigInteger('$id')->primary();
                        $table->unsignedBigInteger('$revision')->index();

                        foreach ($post as $key => $val) {
                            self::addColumn($table, $key, $val['type']);
                        }
                    });

            }

            // batchをtrueに スキップしたものも含めてすべてマイグレート済フラグを立てる
            \App\Model\Fields::where([
                    'appId' => $app['appId'],
                    'batch' => false,
                ])->update([
                        'batch' => true,
                    ]);
        }
    }

    /**
     * add schema to table
     * @param \Illuminate\Database\Schema\Blueprint $table
     * @param string $key
     * @param string $type
     */
    private static function addColumn(\Illuminate\Database\Schema\Blueprint &$table, string $key, string $type)
    {
        switch (self::TYPE_MAP[$type]) {
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
