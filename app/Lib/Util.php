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
            'pre' => self::arrayDiffAssocRecursive($pre, $post),
            'post' => self::arrayDiffAssocRecursive($post, $pre),
        ];

        if ($diff == ['pre' => null, 'post' => null]) {
            return [];
        }

        return $diff;
    }

    /**
     * array_diff_assoc()を多次元配列対応
     */
    private static function arrayDiffAssocRecursive(array $pre, array $post)
    {
        $diff = [];
        foreach ($pre as $key => $val) {
            if (! isset($post[$key])) {
                $post[$key] = null;
            }
            // jsonの場合はarray展開する
            if (self::isJson($val)) {
                $val = json_decode($val, true);
            }
            if (self::isJson($post[$key])) {
                $post[$key] = json_decode($post[$key], true);
            }
            if (! is_array($val)) {
                if ($val != $post[$key]) {
                    $diff[$key] = $val;
                }
            } else {
                // array
                if (! is_array($post[$key])) {
                    $diff[$key] = $val;
                } else {
                    $tmp = self::arrayDiffAssocRecursive($val, $post[$key]);
                    if ($tmp) {
                        $diff[$key] = $tmp;
                    }
                }
            }
        }

        return $diff;
    }

    private static function isJson($string) {
        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }
}
