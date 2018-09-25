<?php

namespace App\Lib;

/**
 * ユーティリティ
 */
class Util
{
    /**
     * DBとAPIで取得した値との比較をするために、booleanを数値にキャスト変換
     *
     * @param array $arr
     * @return array
     */
    public static function castForDb(array $arr)
    {
        foreach ($arr as &$val) {
            if (is_bool($val)) {
                $val = (int)$val;
            }
        }
        return $arr;
    }

    /**
     * 差分比較
     *
     * @param array $pre
     * @param array $post
     * @return array
     */
    public static function arrayDiff(array $pre, array $post)
    {
        $diff = [
            'pre' => array_diff($pre, $post),
            'post' => array_diff($post, $pre),
        ];

        if ($diff == ['pre' => null, 'post' => null]) {
            return [];
        }

        return $diff;
    }
}
