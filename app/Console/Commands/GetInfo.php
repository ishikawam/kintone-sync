<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * アプリ一覧を取得、DBに同期保存する
 * スキーマの変更も反映する
 * スキーマの更新、レコードの更新の操作ログを残す
 */
class GetInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kintone:get-info';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';


    // KintoneApi
    private $api;

    // spaceIds
    private $spaceIds;

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
        $this->api = new \CybozuHttp\Api\KintoneApi(new \CybozuHttp\Client(config('services.kintone')));

        $this->info('getApps');
        $apps = $this->getApps();

        $spaceIds = array_filter(array_unique(array_map(function ($c) {
            return $c['spaceId'];
        }, $apps)));

        $this->info('getSpaces');
        $this->getSpaces($spaceIds);
    }

    /**
     * アプリ情報をDBに保存
     *
     * @return array
     */
    private function getApps()
    {
        $apps = $this->api->apps()->get()['apps'];
        // キーをappIdに
        $apps = array_combine(array_column($apps, 'appId'), $apps);

        $spaceIds = [];
        foreach ($apps as $app) {
            $row = \App\Model\Apps::firstOrNew(['appId' => $app['appId']]);
            $preArray = $row->toArray();
            $row->appId = $app['appId'];
            $row->code = $app['code'];
            $row->name = $app['name'];
            $row->description = $app['description'];
            $row->createdAt = $app['createdAt'];
            $row->{'creator/code'} = $app['creator']['code'];
            $row->{'creator/name'} = $app['creator']['name'];
            $row->modifiedAt = $app['modifiedAt'];
            $row->{'modifier/code'} = $app['modifier']['code'];
            $row->{'modifier/name'} = $app['modifier']['name'];
            $row->spaceId = $app['spaceId'];
            $row->threadId = $app['threadId'];
            $postArray = self::castForDb($row->toArray());
            $row->save();

            // 差分比較
            $diff = [
                'pre' => array_diff($preArray, $postArray),
                'post' => array_diff($postArray, $preArray),
            ];
            if ($diff != ['pre' => null, 'post' => null]) {
                dump($row->appId, $diff);
            }

        }

        // 次にkintoneで削除されたレコードを検索してDBのレコードを削除
        foreach (\App\Model\Apps::all() as $row) {
            if (! isset($apps[$row->appId])) {
                dump('deleted!', $row->toArray());
                $row->delete();
            }
        }

        return $apps;
    }

    /**
     * スペース情報をDBに保存
     *
     * @params int[] $spaceIds
     */
    private function getSpaces(array $spaceIds)
    {
        foreach ($spaceIds as $spaceId) {
            $space = $this->api->space()->get($spaceId);

            $row = \App\Model\Spaces::firstOrNew(['id' => $space['id']]);
            $preArray = $row->toArray();
            $row->id = $space['id'];
            $row->defaultThread = $space['defaultThread'];
            $row->name = $space['name'];
            $row->isPrivate = $space['isPrivate'];
            $row->{'creator/code'} = $space['creator']['code'];
            $row->{'creator/name'} = $space['creator']['name'];
            $row->{'modifier/code'} = $space['modifier']['code'];
            $row->{'modifier/name'} = $space['modifier']['name'];
            $row->memberCount = $space['memberCount'];
            $row->coverType = $space['coverType'];
            $row->coverKey = $space['coverKey'];
            $row->coverUrl = $space['coverUrl'];
            $row->body = $space['body'];
            $row->useMultiThread = $space['useMultiThread'];
            $row->isGuest = $space['isGuest'];
            $row->fixedMember = $space['fixedMember'];
            // $space['attachedApps'] は使用しない
            $postArray = self::castForDb($row->toArray());
            $row->save();

            // 差分比較
            $diff = [
                'pre' => array_diff($preArray, $postArray),
                'post' => array_diff($postArray, $preArray),
            ];
            if ($diff != ['pre' => null, 'post' => null]) {
                dump($row->id, $diff);
            }
        }

        // 次にkintoneで削除されたレコードを検索してDBのレコードを削除
        foreach (\App\Model\Spaces::all() as $row) {
            if (! in_array($row->id, $spaceIds)) {
                dump('deleted!', $row->toArray());
                $row->delete();
            }
        }
    }

    /**
     * DBとAPIで取得した値との比較をするために、booleanを数値にキャスト変換
     */
    private static function castForDb($arr)
    {
        foreach ($arr as &$val) {
            if (is_bool($val)) {
                $val = (int)$val;
            }
        }
        return $arr;
    }
}
