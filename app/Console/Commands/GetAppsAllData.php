<?php

namespace App\Console\Commands;

/**
 * アプリのすべてのレコードを取得し、DBにGET同期保存する
 * レコードの更新の操作ログを残す
 *
 * レコードの新規追加、更新はAPI発行数を絞って取得できるのでそちらに任せる。> GetAppsUpdatedData
 * これは1日1回程度実行する。通常のデータ更新作業ならやらなくても整合保つはず。
 * なお、スキーマの変更があった場合はCreateAndUpdateAppTablesから直接アプリ指定して実行される。
 * APIは、例えば20,000レコードある場合APIは40アクセス消費する。
 * 直接DBをいじった場合に強制同期したいときはfieldsのレコードを削除する等して対応する。
 */
class GetAppsAllData extends \App\Console\Base
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
            // テーブル名はappId
            $tableName = sprintf('app_%010d', $app->appId);
            if (! \Schema::hasTable($tableName)) {
                throw new \Exception('テーブル ' . $tableName . ' が存在しません。kintone:get-info, kintone:create-and-update-app-tablesを先に実行してください。それでもうまくいかない場合はfieldsテーブルを削除してから再度それぞれ実行してください。');
            }

            // DB
            // ここメモリを結構使う。 "Allowed memory size of〜" のエラーが出たら調整すること
            $rows = \DB::table($tableName)
                ->get()
                ->keyBy(self::PRIMARY_KEY_NAME);

            // 全件取得
            $totalCount = 0;
            $offset = 0;
            $ids = [];  // あとで削除判定に使用する
            while ($totalCount >= $offset) {
                $records = $this->api->records()
                    ->get($app->appId, 'limit ' . self::LIMIT_READ . ' offset ' . $offset);

                if ($offset == 0) {
                    // 初回
                    $totalCount = $records['totalCount'];
                    $this->info(sprintf('%s		%s件', $app->name, number_format($totalCount)));
                }

                $offset += self::LIMIT_READ;

                echo('.');

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

                    $preArray = (array)($rows[$postArray[self::PRIMARY_KEY_NAME]] ?? []);

                    // kintoneからnullのものは入ってこないので合わせる
                    $preArray = array_filter($preArray, function($c){return !is_null($c);});
                    // 逆もしかり…
                    $postArray = array_filter(\App\Lib\Util::castForDb($postArray), function($c){return !is_null($c);});

                    // 差分をみてinsert and update
                    $this->insertAndUpdate($tableName, $app->appId, $preArray, $postArray);

                    // 削除用にidを保管
                    $ids[$record['$id']['value']] = true;
                }
            }

            // 削除
            foreach (\DB::table($tableName)->get() as $val) {
                if (! isset($ids[$val->{'$id'}])) {
                    \Log::info([
                            'delete record. APP: ' . $app->appId,
                            $val,
                        ]);
                    echo('D');
                    \DB::table($tableName)
                        ->where('$id', $val->{'$id'})
                        ->delete();
                }
            }

            $this->info('');
        }
    }
}
