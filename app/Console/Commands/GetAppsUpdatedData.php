<?php

namespace App\Console\Commands;

/**
 * アプリの更新されたレコードを取得し、DBにGET同期保存する
 * 更新があった場合はinsert, updateする
 * レコードの更新の操作ログを残す
 *
 * これは5分に1回程度。(1アプリにつき1日最低288アクセス消費)
 * 直接DBをいじった場合に強制同期したいときは、比較用キャッシュを削除する
 */
class GetAppsUpdatedData extends \App\Console\Base
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
    protected $description = 'アプリの更新されたのレコードを取得保存';


    // KintoneApi
    private $api;


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

        $appId = $this->argument('appId');

        $this->getAppsData($appId);

        $this->question('end. ' . __CLASS__);
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
            // テーブル名はappId
            $tableName = sprintf('app_%010d', $app->appId);
            if (! \Schema::hasTable($tableName)) {
                throw new \Exception('テーブル ' . $tableName . ' が存在しません。kintone:get-info, kintone:create-and-update-app-tablesを先に実行してください。それでもうまくいかない場合はfieldsテーブルを削除してから再度それぞれ実行してください。');
            }

            // カラム定義をコメントから取得
            $columnNameUpdatedTime = collect(\DB::select('SHOW FULL columns FROM ' . $tableName))
                ->firstWhere('Comment', 'UPDATED_TIME')  // 更新日時, Updated_datetime
                ->Field;

            // DBから最終更新日を取得
            $latest = \DB::table($tableName)
                ->orderByDesc($columnNameUpdatedTime)
                ->first([$columnNameUpdatedTime]);
            if ($latest == null) {
                $whereLatest = '';
            } else {
                $whereLatest = ' ' . $columnNameUpdatedTime . ' > "' . $latest->$columnNameUpdatedTime . '" ';
            }

            // 更新分を取得
            $totalCount = 0;
            $offset = 0;
            $lf = false;
            while ($totalCount >= $offset) {
                $records = $this->api->records()
                    ->get($app->appId, $whereLatest . 'limit ' . self::LIMIT_READ . ' offset ' . $offset);

                if ($offset == 0) {
                    // 初回
                    $totalCount = $records['totalCount'];
                    $this->info(sprintf('%s		%s件', $app->name, number_format($totalCount)));
                }

                $offset += self::LIMIT_READ;

                // insert update
                foreach ($records['records'] as $record) {
                    $postArray = [];
                    foreach ($record as $key => $val) {
                        // キーを取得してカラム名として登録。2階層以下はコロン:で区切って別カラムにしたかったが、保留
                        if (is_array($val['value'])) {
                            $postArray[$key] = json_encode($val['value']);  // 一旦jsonで記録
                        } else {
                            $postArray[$key] = $val['value'];
                        }
                    }

                    $preArray = (array)\DB::table($tableName)
                        ->where('$id', $postArray['$id'])
                        ->select()
                        ->first();

                    // kintoneからnullのものは入ってこないので合わせる
                    $preArray = array_filter($preArray, function($c){return !is_null($c);});
                    // 逆もしかり…
                    $postArray = array_filter(\App\Lib\Util::castForDb($postArray), function($c){return !is_null($c);});

                    // 差分をみてinsert and update
                    if ($this->insertAndUpdate($tableName, $app->appId, $preArray, $postArray)) {
                        $lf = true;
                    }
                }
            }

            if ($lf) {
                $this->info('');
            }
        }
    }
}
