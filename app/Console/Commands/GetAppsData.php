<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * アプリのすべてのレコードを取得し、DBにGET同期保存する
 * スキーマの変更も反映する
 * スキーマの更新、レコードの更新の操作ログを残す
 *
 * レコードの新規追加、更新はAPI発行数を絞って取得できるのでそちらに任せる。> GetAppsUpdate
 * これは1日1回程度、削除の反映のためにすべてを同期するこれを実行すべき。
 * 例えば20,000レコードある場合APIは40アクセスする。
 * 直接DBをいじった場合に強制同期したいときは、比較用キャッシュを削除する
 * make hoge
 */
class GetAppsData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kintone:get-apps-data';

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

        $this->getAppsData();
    }

    /**
     * 更新のあったアプリの全レコードを取得
     */
    private function getAppsData()
    {
        foreach (\App\Model\Apps::all() as $app) {

            // 全件取得
            $totalCount = 0;
            $offset = 0;
            while ($totalCount >= $offset) {
                $records = $this->api->records()
                    ->get($app['appId'], 'limit ' . self::LIMIT . ' offset ' . $offset);

                $totalCount = $records['totalCount'];

                if ($offset == 0) {
                    // 初回
                    dump(['name' => $app['name'], 'count' => $totalCount]);

                    $appKeys = [];

                    foreach ($records['records'] as $record) {
                        foreach ($record as $key => $val) {

                            // キーを取得してカラム名として登録。2階層以下はコロン:で区切る
                            $type = $val['type'];
                            $val = $val['value'];

                            $appKeys[$key] = ['type' => $type];
/*
                            if (is_array($val)) {
                                foreach ($val as $key2 => $val2) {
                                    // @todo; これじゃだめ。requestによってあったりなかったりするから、やっぱjson突っ込むしかないかな。
                                    // またはレコードの記録時になければカラム追加するロジック。。。うーん。
                                    // これに限らず、データ構造が変わった場合のこと考えなきゃ。
                                    $appKeys[$key . '/' . $key2] = ['type' => $type];
                                }
                            } else {
                                $appKeys[$key] = ['type' => $type];
                            }
*/
                        }
                    }
                    // create table テーブル名はappId
                    $tableName = sprintf('app_%010d', $app['appId']);
                    if (\Schema::hasTable($tableName)) {
                        // スキーマ変更チェック
                    } else {
                        // error
                    }
                }

                $offset += self::LIMIT;

                echo '.';


                // insert update
                foreach ($records['records'] as $record) {
                    $tmp = [];
                    foreach ($record as $key => $val) {
                        // キーを取得してカラム名として登録。2階層以下はコロン:で区切る
                        $type = $val['type'];
//if ($type == 'FILE') {
//var_dump($val);
//}
                        $val = $val['value'];
                        if (is_array($val)) {

                            $tmp[$key] = json_encode($val);
/*
                        foreach ($val as $key2 => $val2) {
                            $tmp[$key . '/' . $key2] = is_array($val2) ? json_encode(['json', $val2]) : $val2;
                        }
*/
                        } else {
                            if (in_array($type, ['NUMBER']) && $val == '') {
                                // NUMBERがからの場合がある
                                $val = null;
                            }
                            $tmp[$key] = $val;
                        }
                    }

                    $recordModel = \DB::table($tableName)
                        ->updateOrInsert([self::PRIMARY_KEY_NAME => $tmp[self::PRIMARY_KEY_NAME]], $tmp);

                }
            }
        }
    }
}
