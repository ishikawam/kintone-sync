<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\KintoneApiWrapper;

/**
 * アプリの削除されたレコードを取得し、DBにGET同期保存する
 * totalCountとtableレコード総数を比較してずれていたらGetAppsAllDataを実行する
 * レコードの削除の操作ログを残す
 *
 * これは5分に1回程度。(1アプリにつき1日最低288アクセス消費)
 * 直接DBをいじった場合に強制同期したいときは、比較用キャッシュを削除する
 */
class GetAppsDeletedData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kintone:get-apps-deleted-data {appId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'アプリの削除されたのレコードを取得保存';


    // KintoneApi
    private $api;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
   {
        $this->question('start. ' . __CLASS__);

        $this->api = new KintoneApiWrapper();

        $appId = $this->argument('appId');

        $this->getAppsData($appId);

        $this->question('end. ' . __CLASS__);
    }

    /**
     * 削除のあったアプリの更新のあったレコードを取得
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

        // ignore apps
        $ignoreApps = config('services.kintone.ignore_apps');

        foreach ($apps as $app) {
            // ignore apps
            if (in_array($app['appId'], $ignoreApps)) {
                continue;
            }

            // テーブル名はappId
            $tableName = sprintf('app_%010d', $app->appId);
            if (! \Schema::hasTable($tableName)) {
                throw new \RuntimeException('テーブル ' . $tableName . ' が存在しません。kintone:get-info, kintone:create-and-update-app-tablesを先に実行してください。それでもうまくいかない場合はfieldsテーブルを削除してから再度それぞれ実行してください。');
            }

            // DBから全件数を取得
            $count = \DB::table($tableName)
                ->count();

            // 削除されてるか判定したいだけなので1件のみ取得
            $records = $this->api->recordsByAppId($app->appId)
                ->get($app->appId, 'limit 1');

            $totalCount = $records['totalCount'];
            $this->info(sprintf('%s	%s		%s件, DB %s件', $app->appId, $app->name, number_format($totalCount), number_format($count)));

            // delete
            if ($totalCount < $count) {
                // これは正確な判定はできない。deleteしてinsertした場合等。簡易チェックで正確なのはGetAppsAllDataに任せる。
                $this->comment('...waiting 2 seconds... (app:' . $app->appId);
                sleep(2);
                $this->call('kintone:get-apps-all-data', [
                        'appId' => $app->appId,
                    ]);
            }
        }
    }
}
