<?php

namespace App\Lib;

use CybozuHttp\Api\KintoneApi;
use CybozuHttp\Client;
use CybozuHttp\Api\Kintone\Apps;
use CybozuHttp\Api\Kintone\App;
use CybozuHttp\Api\Kintone\Records;
use CybozuHttp\Api\Kintone\Space;

/**
 * KintoneApiWrapper
 * token認証かユーザー認証かを動的に切り替え。token認証が対応していればそちらを優先する。
 */
class KintoneApiWrapper extends KintoneApi
{
    private $apps;

    private $app;

    private $space;

    private $records;

    public function __construct()
    {
        // parent::__construct($client);  // ここでは呼ばない
    }

    /**
     * @return Apps
     */
    public function apps(): Apps
    {
        if (isset($this->apps)) {
            return $this->apps;
        }

        $config = config('services.kintone.login');
        if (!($config['login'] && $config['password'])) {
            throw new \RuntimeException('アプリ一覧を操作するにはパスワード認証が必要です');
        }
        $client = new Client($config);
        $this->apps = new Apps($client);

        return $this->apps;
    }

    /**
     * @return Space
     */
    public function space(): Space
    {
        if (isset($this->space)) {
            return $this->space;
        }

        $config = config('services.kintone.login');
        if (!($config['login'] && $config['password'])) {
            throw new \RuntimeException('スペースを操作するにはパスワード認証が必要です');
        }
        $client = new Client($config);
        $this->space = new Space($client);

        return $this->space;
    }

    /**
     * @param  int  $id
     * @return App
     */
    public function appById(int $id): App
    {
        if (isset($this->app[$id])) {
            return $this->app[$id];
        }

        $config = config('services.kintone.login');

        // tokenがあれば使う。なければパスワード認証
        if (isset($config['tokens'][$id])) {
            $config['token'] = $config['tokens'][$id];
            $config['use_api_token'] = true;
        }

        $client = new Client($config);
        $this->app[$id] = new App($client);
        return $this->app[$id];
    }

    /**
     * @param  int  $id
     * @return Records
     */
    public function recordsByAppId(int $id): Records
    {
        if (isset($this->records[$id])) {
            return $this->records[$id];
        }

        $config = config('services.kintone.login');

        // tokenがあれば使う。なければパスワード認証
        if (isset($config['tokens'][$id])) {
            $config['token'] = $config['tokens'][$id];
            $config['use_api_token'] = true;
        }

        $client = new Client($config);
        $this->records[$id] = new Records($client);
        return $this->records[$id];
    }
}
