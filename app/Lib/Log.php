<?php

namespace App\Lib;

/**
 * ログ
 * 整形するため拡張
 */
class Log extends \Illuminate\Support\Facades\Log
{
    /**
     * info
     */
    public static function info($message, array $context = [])
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        parent::info($message, $context);
    }

    /**
     * warn
     */
    public static function warn($message, array $context = [])
    {
        if (is_array($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        parent::warn($message, $context);
    }
}
