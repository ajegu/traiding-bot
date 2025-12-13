<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Bot Status
    |--------------------------------------------------------------------------
    */
    'enabled' => env('BOT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Trading Configuration
    |--------------------------------------------------------------------------
    */
    'trading' => [
        'symbol' => env('BOT_SYMBOL', 'BTCUSDT'),
        'amount' => (float) env('BOT_AMOUNT', 100),
        'strategy' => env('BOT_STRATEGY', 'rsi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | RSI Strategy Configuration
    |--------------------------------------------------------------------------
    */
    'rsi' => [
        'period' => (int) env('BOT_RSI_PERIOD', 14),
        'oversold' => (int) env('BOT_RSI_OVERSOLD', 30),
        'overbought' => (int) env('BOT_RSI_OVERBOUGHT', 70),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moving Average Strategy Configuration
    |--------------------------------------------------------------------------
    */
    'ma' => [
        'short_period' => (int) env('BOT_MA_SHORT', 50),
        'long_period' => (int) env('BOT_MA_LONG', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_trades_per_day' => (int) env('BOT_MAX_TRADES_DAY', 50),
        'max_amount_per_trade' => (float) env('BOT_MAX_AMOUNT_TRADE', 1000),
        'min_balance_usdt' => (float) env('BOT_MIN_BALANCE', 100),
        'cooldown_minutes' => (int) env('BOT_COOLDOWN_MINUTES', 5),
    ],
];
