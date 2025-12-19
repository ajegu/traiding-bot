<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AWS Services
    |--------------------------------------------------------------------------
    */

    'aws' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DynamoDB Tables
    |--------------------------------------------------------------------------
    */

    'dynamodb' => [
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
        'endpoint' => env('DYNAMODB_ENDPOINT'),
        'tables' => [
            'trades' => env('DYNAMODB_TABLE_TRADES', 'trading-bot-dev-trades'),
            'bot_config' => env('DYNAMODB_TABLE_BOT_CONFIG', 'trading-bot-dev-bot-config'),
            'reports' => env('DYNAMODB_TABLE_REPORTS', 'trading-bot-dev-reports'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SSM Parameter Store
    |--------------------------------------------------------------------------
    */

    'ssm' => [
        'prefix' => env('SSM_PARAMETER_PREFIX', '/trading-bot/dev'),
        'parameters' => [
            'binance_api_key' => '/binance/api_key',
            'binance_api_secret' => '/binance/api_secret',
            'telegram_bot_token' => '/telegram/bot_token',
            'telegram_chat_id' => '/telegram/chat_id',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SNS Topics
    |--------------------------------------------------------------------------
    */

    'sns' => [
        'enabled' => env('SNS_ENABLED', true),
        'topics' => [
            'trade_alerts' => env('SNS_TOPIC_TRADE_ALERTS'),
            'error_alerts' => env('SNS_TOPIC_ERROR_ALERTS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SQS Queues
    |--------------------------------------------------------------------------
    */

    'sqs' => [
        'queues' => [
            'orders' => env('SQS_QUEUE_ORDERS'),
            'notifications' => env('SQS_QUEUE_NOTIFICATIONS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Binance API
    |--------------------------------------------------------------------------
    */

    'binance' => [
        'api_key' => env('BINANCE_API_KEY'),
        'api_secret' => env('BINANCE_API_SECRET'),
        'testnet' => env('BINANCE_TESTNET', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot
    |--------------------------------------------------------------------------
    */

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'enabled' => env('TELEGRAM_ENABLED', false),
        'api_url' => 'https://api.telegram.org/bot',
    ],

];
