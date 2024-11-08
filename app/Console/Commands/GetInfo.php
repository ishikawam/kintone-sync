<?php

namespace App\Console\Commands;

use App\Lib\KintoneApiWrapper;
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
    private KintoneApiWrapper $api;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->question('start. '.__CLASS__);

        $this->api = new KintoneApiWrapper;

        // アプリ
        $this->info('getApps');
        $apps = $this->getApps();
        $this->info(sprintf('apps count: %s', count($apps)));

        // スペース
        $spaceIds = array_filter(array_unique(array_map(function ($c) {
            return $c['spaceId'];
        }, $apps)));

        $this->info(sprintf('getSpaces (count: %s)', count($spaceIds)));
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

        $this->question('end. '.__CLASS__);
    }

    /**
     * アプリ情報をDBに保存
     *
     * @return array<int, mixed>
     */
    private function getApps(): array
    {
        try {
            $apps = $this->api->apps()->get()['apps'];
        } catch (\RuntimeException $e) {
            // パスワード認証できない場合
            $apps = [];
            foreach (config('services.kintone.login.tokens') as $id => $val) {
                $apps[] = $this->api->appById($id)->get($id);
            }
        }
        // キーをappIdに
        $apps = array_combine(array_column($apps, 'appId'), $apps);

        // ignore apps
        $ignoreApps = config('services.kintone.ignore_apps');
        $includeApps = config('services.kintone.include_apps');

        foreach ($apps as $key => $app) {
            // ignore apps
            if ($ignoreApps !== ['*'] && in_array($app['appId'], $ignoreApps)) {
                unset($apps[$key]);

                continue;
            }
            if ($ignoreApps === ['*'] && ! in_array($app['appId'], $includeApps)) {
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
            $postArray = \App\Lib\Util::castForDb($row->toArray());
            $row->save();

            // 差分比較
            if ($diff = \App\Lib\Util::arrayDiff($preArray, $postArray)) {
                \Log::info('diff app: '.$row->appId, $diff);
                $this->comment('diff app: '.$row->appId);
            }
        }

        // 次にkintoneで削除されたレコードを検索してDBのレコードを削除
        foreach (\App\Model\Apps::all() as $row) {
            if (! isset($apps[$row->appId])) {
                \Log::info('delete app', $row->toArray());
                $this->comment('delete app: '.$row->appId);
                $row->delete();
            }
        }

        return $apps;
    }

    /**
     * スペース情報をDBに保存
     *
     * @param  int[]  $spaceIds
     */
    private function getSpaces(array $spaceIds): void
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
            $postArray = \App\Lib\Util::castForDb($row->toArray());
            $row->save();

            // 差分比較
            if ($diff = \App\Lib\Util::arrayDiff($preArray, $postArray)) {
                \Log::info('diff spaces: '.$row->id, $diff);
                $this->comment('diff spaces: '.$row->id);
            }
        }

        // 次にkintoneで削除されたレコードを検索してDBのレコードを削除
        foreach (\App\Model\Spaces::all() as $row) {
            if (! in_array($row->id, $spaceIds)) {
                \Log::info('delete spaces', $row->toArray());
                $this->comment('delete spaces: '.$row->id);
                $row->delete();
            }
        }
    }

    /**
     * フォーム情報をDBに保存
     *
     * @param  int[]  $appIds
     */
    private function getForm(array $appIds): void
    {
        foreach ($appIds as $appId) {
            $data = $this->api->appById($appId)->getForm($appId);
            $row = \App\Model\Form::firstOrNew(['appId' => $appId]);
            $preArray = $row->toArray();
            $row->appId = $appId;
            $row->properties = json_encode($data, JSON_UNESCAPED_UNICODE);
            $postArray = \App\Lib\Util::castForDb($row->toArray());
            $row->save();

            // 差分比較
            if ($diff = \App\Lib\Util::arrayDiff($preArray, $postArray)) {
                \Log::info('diff form: '.$row->appId, $diff);
                $this->comment('diff form: '.$row->appId);
            }
        }

        // 次にkintoneで削除されたレコードを検索してDBのレコードを削除
        foreach (\App\Model\Form::all() as $row) {
            if (! in_array($row->appId, $appIds)) {
                \Log::info('delete form', $row->toArray());
                $this->comment('delete form: '.$row->appId);
                $row->delete();
            }
        }
    }

    /**
     * フィールド情報をDBに保存
     * revisionごと保存。batchはmigration進捗フラグとして使用
     *
     * @param  int[]  $appIds
     */
    private function getFields(array $appIds): void
    {
        foreach ($appIds as $appId) {
            $data = $this->api->appById($appId)->getFields($appId);

            $row = \App\Model\Fields::firstOrCreate([
                'appId' => $appId,
                'revision' => $data['revision'],
            ], [
                'properties' => json_encode($data['properties'], JSON_UNESCAPED_UNICODE),
            ]);

            if ($row->batch === null) {
                $this->comment('new fields: '.$appId.', '.$data['revision']);
            }
        }
    }

    /**
     * レイアウト情報をDBに保存
     * revisionごと保存。batchは現在未使用
     *
     * @param  int[]  $appIds
     */
    private function getLayout(array $appIds): void
    {
        foreach ($appIds as $appId) {
            $data = $this->api->appById($appId)->getLayout($appId);
            $row = \App\Model\Layout::firstOrCreate([
                'appId' => $appId,
                'revision' => $data['revision'],
            ], [
                'layout' => json_encode($data['layout'], JSON_UNESCAPED_UNICODE),
            ]);
        }
    }
}
