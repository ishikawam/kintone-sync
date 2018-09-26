<?php

namespace App\Console;

use Illuminate\Console\Command;

/**
 * 共通
 */
class Base extends Command
{
    /**
     * const
     */
    const LIMIT = 500;  // kintoneの取得レコード数上限
    const PRIMARY_KEY_NAME = 'レコード番号';

    /**
     * kintone, DBの差分を比較して変更or新規追加があればDBを更新する
     * @param string $tableName
     * @param int $appId
     * @param array $preArray
     * @param array $postArray
     */
    public function insertAndUpdate(string $tableName, int $appId, array $preArray, array $postArray)
    {
        if ($diff = \App\Lib\Util::arrayDiff($preArray, $postArray)) {
            if ($preArray) {
                // update
                echo 'U';
                \Log::info(json_encode([
                            'update: ' . $appId . ':' . $postArray[self::PRIMARY_KEY_NAME],
                            $diff,
                        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                try {
                    \DB::table($tableName)
                        ->where(self::PRIMARY_KEY_NAME, $postArray[self::PRIMARY_KEY_NAME])
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
   \Log::info(json_encode([
   'insert: ' . $appId . ':' . $postArray[self::PRIMARY_KEY_NAME],
   $postArray,
   ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
*/
                try {
                    \DB::table($tableName)
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
