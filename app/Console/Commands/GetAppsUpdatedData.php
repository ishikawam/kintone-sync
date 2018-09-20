<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * アプリの更新されたレコードを取得し、DBにGET同期保存する
 * 更新があった場合はinsert, updateする
 * その上でtotalCountとtableレコード総数を比較してずれていたら仕方ないのでGetAppsAllDataを実行する
 * レコードの更新の操作ログを残す
 *
 * これは5分に1回程度。(1日288アクセス消費)
 * 直接DBをいじった場合に強制同期したいときは、比較用キャッシュを削除する
 */
class GetAppsUpdatedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kintone:get-apps-updated-data {appId?}';

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
     * 更新のあったアプリの更新のあったレコードを取得
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

            // DBから最終更新日、件数を取得
            $latest = \DB::table($tableName)
                ->orderByDesc('更新日時')
                ->first(['更新日時']);

            // 更新分を取得
            $totalCount = 0;
            $offset = 0;
            while ($totalCount >= $offset) {
                $records = $this->api->records()
                    ->get($app->appId, '更新日時 > "' . $latest->更新日時 . '" limit ' . self::LIMIT . ' offset ' . $offset);

                if ($offset == 0) {
                    // 初回
                    $totalCount = $records['totalCount'];
                    $this->info(sprintf("\n%s		%s件", $app->name, number_format($totalCount)));
                }

                $offset += self::LIMIT;

                // insert update
                foreach ($records['records'] as $record) {
                    $tmp = [];
                    foreach ($record as $key => $val) {
                        // キーを取得してカラム名として登録。2階層以下はコロン:で区切って別カラムにしたかったが、保留
                        if (is_array($val['value'])) {
                            $tmp[$key] = json_encode($val['value']);  // 一旦jsonで記録
                        } else {
                            if (in_array($val['type'], ['NUMBER']) && $val['value'] == '') {
                                // NUMBERがからの場合がある
                                $val['value'] = null;
                            }
                            $tmp[$key] = $val['value'];
                        }
                    }

                    $recordModel = \DB::table($tableName)
                        ->updateOrInsert([self::PRIMARY_KEY_NAME => $tmp[self::PRIMARY_KEY_NAME]], $tmp);
                }
            }
        }
    }
}
