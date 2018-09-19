<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * アプリ一覧、スペースの情報、等を取得、DBにGET同期保存する
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
    protected $description = 'アプリ一覧、スペースの情報、等を取得保存';


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
        $this->api = new \CybozuHttp\Api\KintoneApi(new \CybozuHttp\Client(config('services.kintone.login')));

        // アプリ
        $this->info('getApps');
        $apps = $this->getApps();

        // スペース
        $spaceIds = array_filter(array_unique(array_map(function ($c) {
            return $c['spaceId'];
        }, $apps)));

        $this->info('getSpaces');
        $this->getSpaces($spaceIds);

        // フォーム
        $this->info('getForm');
        $this->getForm(array_keys($apps));

        // フィールド
        $this->info('getFields');
        $this->getFields(array_keys($apps));

        // レイアウト
        $this->info('getLayout');
        $this->getLayout(array_keys($apps));
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

        // ignore apps
        $ignoreApps = config('services.kintone.ignore_apps');

        $spaceIds = [];
        foreach ($apps as $key => $app) {
            // ignore apps
            if (in_array($app['appId'], $ignoreApps)) {
                unset($apps[$key]);
                continue;
            }

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
     * フォーム情報をDBに保存
     *
     * @params int[] $appIds
     */
    private function getForm(array $appIds)
    {
        foreach ($appIds as $appId) {
            $data = $this->api->app()->getForm($appId);
            $row = \App\Model\Form::firstOrNew(['appId' => $appId]);
            $preArray = $row->toArray();
            $row->appId = $appId;
            $row->properties = json_encode($data, JSON_UNESCAPED_UNICODE);
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
        foreach (\App\Model\Form::all() as $row) {
            if (! in_array($row->appId, $appIds)) {
                dump('deleted!', $row->toArray());
                $row->delete();
            }
        }
    }

    /**
     * フィールド情報をDBに保存
     * (当たり前のはずだが)同じrevisionで内容は変わらないはず @todo; 確認
     *
     * @params int[] $appIds
     */
    private function getFields(array $appIds)
    {
        foreach ($appIds as $appId) {
            $data = $this->api->app()->getFields($appId);
            $row = \App\Model\Fields::firstOrCreate([
                    'appId' => $appId,
                    'revision' => $data['revision'],
                ], [
                    'properties' => json_encode($data['properties'], JSON_UNESCAPED_UNICODE),
                ]);
        }
/* 消す必要ない
        // 次にkintoneで削除されたレコードを検索してDBのレコードを削除
        foreach (\App\Model\Fields::all() as $row) {
            if (! in_array($row->appId, $appIds)) {
                $row->delete();
            }
        }
*/
    }

    /**
     * レイアウト情報をDBに保存
     * (当たり前のはずだが)同じrevisionで内容は変わらないはず @todo; 確認
     *
     * @params int[] $appIds
     */
    private function getLayout(array $appIds)
    {
        foreach ($appIds as $appId) {
            $data = $this->api->app()->getLayout($appId);
            $row = \App\Model\Layout::firstOrCreate([
                    'appId' => $appId,
                    'revision' => $data['revision'],
                ], [
                    'layout' => json_encode($data['layout'], JSON_UNESCAPED_UNICODE),
                ]);
        }
/* 消す必要ない

        // 次にkintoneで削除されたレコードを検索してDBのレコードを削除
        foreach (\App\Model\Layout::all() as $row) {
            if (! in_array($row->appId, $appIds)) {
                $row->delete();
            }
        }
*/
    }

    /**
     * DBとAPIで取得した値との比較をするために、booleanを数値にキャスト変換
     * @params array $arr
     */
    private static function castForDb(array $arr)
    {
        foreach ($arr as &$val) {
            if (is_bool($val)) {
                $val = (int)$val;
            }
        }
        return $arr;
    }
}
