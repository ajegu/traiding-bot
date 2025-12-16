<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AWS Services
    |--------------------------------------------------------------------------
    */

    'aws' => [
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
        'version' => 'latest',

        // Credentials (optional if using IAM role on Lambda)
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DynamoDB Tables
    |--------------------------------------------------------------------------
    */

    'dynamodb' => [
        'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
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

        // API URLs
        'urls' => [
            'api' => env('BINANCE_API_URL', 'https://api.binance.com'),
            'testnet' => env('BINANCE_TESTNET_URL', 'https://testnet.binance.vision'),
        ],

        // Rate limiting
        'rate_limit' => [
            'requests_per_minute' => 1200,
            'orders_per_second' => 10,
            'orders_per_day' => 200000,
        ],
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

        // Rate limiting
        'rate_limit' => [
            'messages_per_second' => 1,
            'messages_per_minute_per_chat' => 30,
        ],
    ],

];
