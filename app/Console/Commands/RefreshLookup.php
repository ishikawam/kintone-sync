<?php

namespace App\Console\Commands;

use App\Lib\KintoneApiWrapper;

/**
 * ルックアップの再取得を一括実施
 */
class RefreshLookup extends \App\Console\Base
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kintone:refresh-lookup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ルックアップの再取得を一括実施';

    // KintoneApi
    private KintoneApiWrapper $api;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->question('start. '.__CLASS__);

        $this->api = new KintoneApiWrapper;

        $this->refreshLookup();

        $this->question('end. '.__CLASS__);
    }

    /**
     * ルックアップの再取得を一括実施
     */
    private function refreshLookup(): void
    {
        $refreshLookups = config('services.kintone.custom.refresh_lookup');

        foreach ($refreshLookups as $refreshLookup) {
            $app = \App\Model\Apps::find($refreshLookup['app']);
            $query = $refreshLookup['query'];

            $this->info(sprintf('%s: %s	\'%s\'', $app->appId, $app->name, $query));

            // fields
            $fields = \App\Model\Fields::where('appId', $app->appId)
                ->orderByDesc('id')
                ->first();
            $field = json_decode($fields->properties);
            $codes = [];
            foreach ($field as $row) {
                if (isset($row->lookup) && isset($row->code)) {
                    // ルックアップのコードをすべて取得 現状コードを指定しての一括更新はできない
                    $codes[] = $row->code;
                }
            }

            $this->line('ルックアップコード: '.implode(', ', $codes));

            // apiから取得
            $totalCount = 0;
            $offset = 0;
            $rows = [];  // 対象全部
            while ($totalCount >= $offset) {
                $records = $this->api->recordsByAppId($app->appId)
                    ->get($app->appId, $query.' limit '.self::LIMIT_READ.' offset '.$offset);

                if ($offset == 0) {
                    // 初回
                    $totalCount = $records['totalCount'];
                    $this->line(sprintf('対象: %s件', number_format($totalCount)));
                }

                $offset += self::LIMIT_READ;

                foreach ($records['records'] as $record) {
                    // lookupを更新すれば再読込してくれる
                    $rows[] = [
                        'id' => $record['$id']['value'],
                        'record' => [
                            $codes[0] => [
                                'value' => $record[$codes[0]]['value'],
                            ],
                        ],
                    ];
                }
            }

            // limitに分けて実行
            foreach (array_chunk($rows, self::LIMIT_WRITE) as $val) {
                echo '.';

                $res = $this->api->recordsByAppId($app->appId)
                    ->put($app->appId, $val);
                \Log::info('refresh lookup', [$app->appId, $val, $res]);
            }

            $this->info('');
        }
    }
}
