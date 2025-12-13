<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | AWS DynamoDB Configuration
    |--------------------------------------------------------------------------
    */
    'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'trades' => env('DYNAMODB_TABLE_TRADES', 'trading-bot-dev-trades'),
        'bot_config' => env('DYNAMODB_TABLE_BOT_CONFIG', 'trading-bot-dev-bot-config'),
        'reports' => env('DYNAMODB_TABLE_REPORTS', 'trading-bot-dev-reports'),
    ],
];
