<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bot Status
    |--------------------------------------------------------------------------
    |
    | Enable or disable the trading bot. When disabled, the bot will not
    | execute any trades but will still run analysis and log signals.
    |
    */

    'enabled' => env('BOT_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Trading Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the default trading pair and amount per trade.
    |
    */

    'trading' => [
        'symbol' => env('BOT_SYMBOL', 'BTCUSDT'),
        'amount' => (float) env('BOT_AMOUNT', 100), // Amount in quote currency (USDT)
        'max_trades_per_day' => (int) env('BOT_MAX_TRADES_PER_DAY', 50),
        'cooldown_minutes' => (int) env('BOT_COOLDOWN_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Strategy Configuration
    |--------------------------------------------------------------------------
    |
    | Available strategies: rsi, ma, combined
    |
    */

    'strategy' => [
        'active' => env('BOT_STRATEGY', 'rsi'),

        'rsi' => [
            'period' => (int) env('BOT_RSI_PERIOD', 14),
            'oversold' => (float) env('BOT_RSI_OVERSOLD', 30),
            'overbought' => (float) env('BOT_RSI_OVERBOUGHT', 70),
        ],

        'ma' => [
            'short_period' => (int) env('BOT_MA_SHORT', 50),
            'long_period' => (int) env('BOT_MA_LONG', 200),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk Management
    |--------------------------------------------------------------------------
    |
    | Configure risk management parameters.
    |
    */

    'risk' => [
        'max_drawdown_percent' => (float) env('BOT_MAX_DRAWDOWN', 10),
        'stop_loss_percent' => (float) env('BOT_STOP_LOSS', 0), // 0 = disabled
        'take_profit_percent' => (float) env('BOT_TAKE_PROFIT', 0), // 0 = disabled
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Limits
    |--------------------------------------------------------------------------
    |
    | Configure safety limits to prevent excessive trading.
    |
    */

    'limits' => [
        'max_trades_per_day' => (int) env('BOT_MAX_TRADES_DAY', 50),
        'max_amount_per_trade' => (float) env('BOT_MAX_AMOUNT_TRADE', 1000),
        'min_balance_usdt' => (float) env('BOT_MIN_BALANCE', 100),
        'cooldown_minutes' => (int) env('BOT_COOLDOWN_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Klines Configuration
    |--------------------------------------------------------------------------
    |
    | Configure candlestick data retrieval.
    |
    */

    'klines' => [
        'interval' => env('BOT_KLINES_INTERVAL', '5m'),
        'limit' => (int) env('BOT_KLINES_LIMIT', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Configure notification settings.
    |
    */

    'notifications' => [
        'trade_executed' => env('BOT_NOTIFY_TRADES', true),
        'errors' => env('BOT_NOTIFY_ERRORS', true),
        'daily_report' => env('BOT_NOTIFY_DAILY_REPORT', true),
    ],

];
