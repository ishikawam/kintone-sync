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
 */
class GetAppsAllData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kintone:get-apps-all-data {appId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'アプリのすべてのレコードを取得保存';


    // KintoneApi
    private $api;

    // const
    const LIMIT = 500;  // kintoneの取得レコード数上限
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

        $appId = $this->argument('appId');

        $this->getAppsData($appId);
    }

    /**
     * 更新のあったアプリの全レコードを取得
     *
     * @param int|null $appId
     */
    private function getAppsData(int $appId = null)
    {
        if ($appId) {
            $apps = [\App\Model\Apps::find($appId)];
        } else {
            $apps = \App\Model\Apps::all();
        }

        foreach ($apps as $app) {
            // create table テーブル名はappId
            $tableName = sprintf('app_%010d', $app->appId);
            if (! \Schema::hasTable($tableName)) {
                throw new \Exception('テーブル ' . $tableName . ' が存在しません。kintone:get-info, kintone:create-and-update-app-tablesを先に実行してください。それでもうまくいかない場合はfieldsテーブルを削除してから再度それぞれ実行してください。');
            }

            // 全件取得
            $totalCount = 0;
            $offset = 0;
            while ($totalCount >= $offset) {
                $records = $this->api->records()
                    ->get($app->appId, 'limit ' . self::LIMIT . ' offset ' . $offset);

                if ($offset == 0) {
                    // 初回
                    $totalCount = $records['totalCount'];
                    $this->info(sprintf("\n%s		%s件", $app->name, number_format($totalCount)));
                }

                $offset += self::LIMIT;

                echo('.');

                // insert update
                foreach ($records['records'] as $record) {
                    $tmp = [];
                    foreach ($record as $key => $val) {
                        // キーを取得してカラム名として登録。2階層以下はコロン:で区切って別カラムにしたかったが、保留
                        $type = $val['type'];
                        $val = $val['value'];
                        if (is_array($val)) {
                            $tmp[$key] = json_encode($val);  // 一旦jsonで記録
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
