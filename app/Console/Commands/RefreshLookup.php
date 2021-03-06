<?php

namespace App\Console\Commands;

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

        $this->refreshLookup();

        $this->question('end. ' . __CLASS__);
    }

    /**
     * ルックアップの再取得を一括実施
     */
    private function refreshLookup()
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
                if (isset($row->lookup)) {
                    // ルックアップのコードをすべて取得 現状コードを指定しての一括更新はできない
                    $codes[] = $row->code;
                }
            }

            $this->line('ルックアップコード: ' . implode(', ', $codes));

            // apiから取得
            $totalCount = 0;
            $offset = 0;
            $rows = [];  // 対象全部
            while ($totalCount >= $offset) {
                $records = $this->api->records()
                    ->get($app->appId, $query . ' limit ' . self::LIMIT_READ . ' offset ' . $offset);

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
                echo('.');

                $res = $this->api->records()
                    ->put($app->appId, $val);
                \Log::info(['refresh lookup', $app->appId, $val, $res]);
            }

            $this->info('');
        }
    }
}
