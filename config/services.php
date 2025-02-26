<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => env('SES_REGION', 'us-east-1'),
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    /**
     * Kintone
     * .envから読むものとconfig/kintone.phpから読むものとを混在させている
     */
    'kintone' => [
        'login' => [
            'domain' => env('KINTONE_DOMAIN', 'cybozu.com'),
            'subdomain' => env('KINTONE_SUBDOMAIN'),
            // パスワード認証
            'login' => env('KINTONE_LOGIN'),
            'password' => env('KINTONE_PASSWORD'),
            // APIトークン認証
            'tokens' => json_decode(env('KINTONE_TOKENS', '{}'), true, 512, JSON_THROW_ON_ERROR),
        ],

        'ignore_apps' => array_filter(explode(',', env('KINTONE_IGNORE_APPS', ''))),
        'include_apps' => array_filter(explode(',', env('KINTONE_INCLUDE_APPS', ''))),

        'custom' => config('kintone'),
    ],
];
