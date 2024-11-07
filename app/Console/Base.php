<?php

namespace App\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 共通
 */
class Base extends Command
{
    /**
     * const
     */
    const LIMIT_READ = 500;  // kintoneの取得レコード数上限

    const LIMIT_WRITE = 100;  // kintoneの書き込みレコード数上限

    /**
     * kintone, DBの差分を比較して変更or新規追加があればDBを更新する
     */
    public function insertAndUpdate(string $tableName, int $appId, array $preArray, array $postArray)
    {
        if ($diff = \App\Lib\Util::arrayDiff($preArray, $postArray)) {
            if ($preArray) {
                // update
                echo 'U';
                \Log::info([
                    'update: '.$appId.':'.$postArray['$id'],
                    $diff,
                ]);
                try {
                    DB::table($tableName)
                        ->where('$id', $postArray['$id'])
                        ->update($postArray);
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() == '42S22' && ($e->errorInfo[1] ?? null) == 1054) {
                        // Column not found で再生成
                        $this->info('');
                        $this->error($e->errorInfo[2]);
                        $this->warn('カラムに変更があったかもしれません。`make get-info & make create-and-update-app-tables` を実行します');
                        $this->call('kintone:get-info');
                        $this->call('kintone:create-and-update-app-tables');
                        $this->info('DONE.');

                        return;
                    } else {
                        throw $e;
                    }
                }
            } else {
                // insert
                echo 'I';
                /* insertのログはいらない
                   \Log::info([
                   'insert: ' . $appId . ':' . $postArray['$id'],
                   $postArray,
                   ]);
                */
                try {
                    DB::table($tableName)
                        ->insert($postArray);
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() == '42S22' && ($e->errorInfo[1] ?? null) == 1054) {
                        // Column not found で再生成
                        $this->info('');
                        $this->error($e->errorInfo[2]);
                        $this->warn('カラムに変更があったかもしれません。`make get-info & make create-and-update-app-tables` を実行します');
                        $this->call('kintone:get-info');
                        $this->call('kintone:create-and-update-app-tables');
                        $this->info('DONE.');

                        return;
                    } else {
                        throw $e;
                    }
                }
            }

            return true;
        }

        return false;
    }
}
