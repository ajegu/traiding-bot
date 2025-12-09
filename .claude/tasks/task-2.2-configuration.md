# Task 2.2 - Configuration (config/bot.php, config/services.php)

## Objectif

Créer les fichiers de configuration Laravel pour le bot de trading et les services AWS.

## Fichiers à créer/modifier

### 1. Configuration du bot

**Créer** : `config/bot.php`

```php
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
```

### 2. Configuration AWS

**Modifier** : `config/services.php`

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
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
```

### 3. Configuration AWS SDK

**Créer** : `config/aws.php` (si non existant après publish)

```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | AWS SDK Configuration
    |--------------------------------------------------------------------------
    |
    | The configuration options set in this file will be passed directly to
    | the `Aws\Sdk` object, from which all client objects are created. This
    | file is published from the aws/aws-sdk-php-laravel package.
    |
    | See: https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_configuration.html
    |
    */

    'credentials' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    'region' => env('AWS_DEFAULT_REGION', 'eu-west-3'),
    'version' => 'latest',

    // SDK specific configurations
    'ua_append' => [
        'L5MOD/' . Aws\Laravel\AwsServiceProvider::VERSION,
    ],

    // HTTP client configuration
    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
    ],

    // Retry configuration
    'retries' => [
        'mode' => 'standard',
        'max_attempts' => 3,
    ],

];
```

### 4. Créer le Service Provider pour le bot

**Créer** : `app/Providers/BotServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class BotServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/bot.php', 'bot'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/bot.php' => config_path('bot.php'),
            ], 'bot-config');
        }
    }
}
```

### 5. Enregistrer le Service Provider

**Modifier** : `bootstrap/providers.php`

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\BotServiceProvider::class,
];
```

## Utilisation

### Accéder à la configuration

```php
// Configuration du bot
$enabled = config('bot.enabled');
$strategy = config('bot.strategy.active');
$rsiPeriod = config('bot.strategy.rsi.period');

// Configuration AWS/Services
$tradesTable = config('services.dynamodb.tables.trades');
$snsTopicArn = config('services.sns.topics.trade_alerts');
$telegramEnabled = config('services.telegram.enabled');

// Configuration Binance
$testnet = config('services.binance.testnet');
```

### Helper pour SSM Parameter Path

```php
// Obtenir le chemin complet d'un paramètre SSM
$prefix = config('services.ssm.prefix');
$apiKeyPath = $prefix . config('services.ssm.parameters.binance_api_key');
// Result: /trading-bot/dev/binance/api_key
```

## Vérification

```bash
# Vérifier la configuration du bot
php artisan config:show bot

# Vérifier la configuration des services
php artisan config:show services

# Vérifier la configuration AWS
php artisan config:show aws

# Cache la configuration (production)
php artisan config:cache

# Vider le cache de configuration
php artisan config:clear
```

## Dépendances

- **Prérequis** : Tâche 2.1 (Laravel setup)
- **Utilisé par** : Toutes les tâches suivantes de la Phase 2

## Checklist

- [ ] Créer `config/bot.php` avec toutes les options
- [ ] Modifier `config/services.php` avec AWS, Binance, Telegram
- [ ] Créer/modifier `config/aws.php` pour AWS SDK
- [ ] Créer `app/Providers/BotServiceProvider.php`
- [ ] Enregistrer le provider dans `bootstrap/providers.php`
- [ ] Mettre à jour `.env.example` avec toutes les variables
- [ ] Tester avec `php artisan config:show bot`
- [ ] Tester avec `php artisan config:show services`
