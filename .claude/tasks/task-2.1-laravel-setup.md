# Task 2.1 - Setup Laravel + Bref + packages AWS

## Objectif

Initialiser le projet Laravel 12 avec Bref pour le déploiement sur AWS Lambda, et installer tous les packages nécessaires.

## Packages à installer

| Package | Version | Description |
|---------|---------|-------------|
| `laravel/laravel` | ^12.0 | Framework Laravel |
| `bref/bref` | ^2.0 | Runtime PHP pour Lambda |
| `bref/laravel-bridge` | ^2.0 | Intégration Laravel/Bref |
| `aws/aws-sdk-php-laravel` | ^3.0 | SDK AWS pour Laravel |
| `jaggedsoft/php-binance-api` | ^1.0 | API Binance |

## Structure finale

```
trading-bot/
├── app/
│   ├── Console/Commands/       # Commandes Artisan (bot:run, report:daily)
│   ├── Contracts/              # Interfaces des services
│   ├── DTOs/                   # Data Transfer Objects (immutables, readonly)
│   ├── Enums/                  # Énumérations PHP 8.4
│   ├── Events/                 # Événements applicatifs
│   ├── Exceptions/             # Exceptions personnalisées par domaine
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/           # Form Requests (validation)
│   ├── Jobs/                   # Jobs asynchrones (SQS)
│   ├── Listeners/              # Listeners d'événements
│   ├── Models/                 # Modèles DynamoDB
│   ├── Providers/              # Service Providers
│   ├── Repositories/           # Accès aux données DynamoDB
│   └── Services/               # Logique métier
│       ├── Binance/
│       ├── Trading/
│       │   ├── Indicators/     # RSI, MA calculators
│       │   └── Strategies/     # Trading strategies
│       └── Notification/
├── config/
│   ├── bot.php                 # Configuration du bot de trading
│   ├── dynamodb.php            # Configuration DynamoDB
│   └── services.php            # Services externes (Binance, Telegram)
├── bootstrap/
├── database/
├── public/
├── resources/views/
├── routes/
├── storage/
├── tests/
│   ├── Unit/                   # Tests unitaires (indicateurs, DTOs)
│   └── Feature/                # Tests d'intégration (services, commandes)
├── terraform/                  # Infrastructure AWS (déjà existant)
├── .claude/                    # Documentation projet (déjà existant)
├── composer.json
└── .env.example
```

> **Note** : Le déploiement sur AWS Lambda est géré via **Terraform** (pas Serverless Framework). Les layers Bref sont configurés dans `terraform/modules/lambda/`.

## Instructions

### Étape 1 : Créer le projet Laravel

```bash
cd /Users/allan/Dev/traiding-bot

# Créer le projet Laravel dans un dossier temporaire
composer create-project laravel/laravel app-temp

# Déplacer les fichiers Laravel (sans écraser les existants)
shopt -s dotglob
mv app-temp/* . 2>/dev/null || true
rm -rf app-temp
```

### Étape 2 : Installer Bref

```bash
composer require bref/bref bref/laravel-bridge --update-with-dependencies

# Publier la configuration Bref
php artisan vendor:publish --tag=bref-config
```

### Étape 3 : Installer AWS SDK

```bash
composer require aws/aws-sdk-php-laravel

# Publier la configuration AWS
php artisan vendor:publish --provider="Aws\Laravel\AwsServiceProvider"
```

### Étape 4 : Installer le package Binance

```bash
composer require jaggedsoft/php-binance-api
```

### Étape 5 : Packages de développement

```bash
# PHPUnit et Mockery sont déjà inclus dans Laravel 12
# Ajouter Pint pour le linting PSR-12
composer require --dev laravel/pint
```

### Étape 6 : Créer la structure des dossiers

```bash
# Structure de base selon laravel.md
mkdir -p app/Console/Commands
mkdir -p app/Contracts
mkdir -p app/DTOs
mkdir -p app/Enums
mkdir -p app/Events
mkdir -p app/Exceptions
mkdir -p app/Http/Controllers
mkdir -p app/Http/Requests
mkdir -p app/Jobs
mkdir -p app/Listeners
mkdir -p app/Models
mkdir -p app/Providers
mkdir -p app/Repositories

# Services métier
mkdir -p app/Services/Binance
mkdir -p app/Services/Trading/Indicators
mkdir -p app/Services/Trading/Strategies
mkdir -p app/Services/Notification

# Structure des tests
mkdir -p tests/Unit/Services/Trading/Indicators
mkdir -p tests/Unit/DTOs
mkdir -p tests/Feature/Console
mkdir -p tests/Feature/Services
```

### Étape 7 : Créer les fichiers de configuration

**Créer** : `config/bot.php`

```php
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
```

**Créer** : `config/dynamodb.php`

```php
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
```

### Étape 8 : Configurer .env.example

**Modifier** : `.env.example`

```env
APP_NAME="Trading Bot"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=debug

# AWS Configuration
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-west-3

# DynamoDB Tables
DYNAMODB_TABLE_TRADES=trading-bot-dev-trades
DYNAMODB_TABLE_BOT_CONFIG=trading-bot-dev-bot-config
DYNAMODB_TABLE_REPORTS=trading-bot-dev-reports

# SSM Parameter Store Prefix
SSM_PARAMETER_PREFIX=/trading-bot/dev

# SNS Topics
SNS_TOPIC_TRADE_ALERTS=
SNS_TOPIC_ERROR_ALERTS=

# SQS Queues
SQS_QUEUE_ORDERS=
SQS_QUEUE_NOTIFICATIONS=

# Binance (for local development - use SSM in production)
BINANCE_API_KEY=
BINANCE_API_SECRET=
BINANCE_TESTNET=true

# Telegram (for local development - use SSM in production)
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
TELEGRAM_ENABLED=false

# Bot Configuration
BOT_ENABLED=false
BOT_STRATEGY=rsi
BOT_SYMBOL=BTCUSDT
BOT_AMOUNT=100

# RSI Settings
BOT_RSI_PERIOD=14
BOT_RSI_OVERSOLD=30
BOT_RSI_OVERBOUGHT=70

# MA Settings
BOT_MA_SHORT=50
BOT_MA_LONG=200

# Safety Limits
BOT_MAX_TRADES_DAY=50
BOT_MAX_AMOUNT_TRADE=1000
BOT_MIN_BALANCE=100
BOT_COOLDOWN_MINUTES=5
```

### Étape 9 : Configurer services.php pour Binance et Telegram

**Modifier** : `config/services.php` (ajouter)

```php
    /*
    |--------------------------------------------------------------------------
    | Binance API Configuration
    |--------------------------------------------------------------------------
    */
    'binance' => [
        'api_key' => env('BINANCE_API_KEY'),
        'api_secret' => env('BINANCE_API_SECRET'),
        'testnet' => env('BINANCE_TESTNET', true),
        'base_url' => env('BINANCE_TESTNET', true)
            ? 'https://testnet.binance.vision'
            : 'https://api.binance.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Configuration
    |--------------------------------------------------------------------------
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'enabled' => env('TELEGRAM_ENABLED', false),
        'base_url' => 'https://api.telegram.org',
    ],
```

### Étape 10 : Optimisations pour Lambda

**Modifier** : `bootstrap/app.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware configuration
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Exception handling configuration
    })
    ->create();
```

**Modifier** : `config/logging.php` (section `channels`)

```php
'stderr' => [
    'driver' => 'monolog',
    'level' => env('LOG_LEVEL', 'debug'),
    'handler' => StreamHandler::class,
    'formatter' => env('LOG_STDERR_FORMATTER'),
    'with' => [
        'stream' => 'php://stderr',
    ],
    'processors' => [PsrLogMessageProcessor::class],
],
```

### Étape 11 : Configurer .gitignore

**Modifier** : `.gitignore` (ajouter)

```gitignore
# Lambda / Bref
.bref/

# IDE
.idea/
.vscode/
*.swp
*.swo

# Environment
.env
.env.local
.env.*.local

# Terraform
terraform/.terraform/
terraform/*.tfstate*
terraform/*.tfvars
terraform/placeholder.zip

# Vendor
/vendor/
/node_modules/

# Storage
/storage/*.key

# Cache
.phpunit.result.cache
Homestead.json
Homestead.yaml
npm-debug.log
yarn-error.log
```

### Étape 12 : Créer les fichiers .gitkeep pour les dossiers vides

```bash
# Créer des fichiers .gitkeep pour conserver les dossiers vides dans git
touch app/Contracts/.gitkeep
touch app/DTOs/.gitkeep
touch app/Enums/.gitkeep
touch app/Events/.gitkeep
touch app/Exceptions/.gitkeep
touch app/Jobs/.gitkeep
touch app/Listeners/.gitkeep
touch app/Repositories/.gitkeep
touch app/Services/Binance/.gitkeep
touch app/Services/Trading/Indicators/.gitkeep
touch app/Services/Trading/Strategies/.gitkeep
touch app/Services/Notification/.gitkeep
```

## Vérification

```bash
# Vérifier l'installation Laravel
php artisan --version

# Vérifier Bref
vendor/bin/bref --version

# Lister les commandes disponibles
php artisan list

# Vérifier la configuration
php artisan config:show app
php artisan config:show bot
php artisan config:show dynamodb

# Tester le serveur local
php artisan serve

# Vérifier le linting
vendor/bin/pint --test
```

## Composer.json final

```json
{
    "name": "trading-bot/binance",
    "type": "project",
    "description": "Binance Trading Bot with Laravel on AWS Lambda",
    "require": {
        "php": "^8.4",
        "laravel/framework": "^12.0",
        "bref/bref": "^2.0",
        "bref/laravel-bridge": "^2.0",
        "aws/aws-sdk-php-laravel": "^3.0",
        "jaggedsoft/php-binance-api": "^1.0"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pint": "^1.13",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.0",
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "test": "php artisan test",
        "lint": "vendor/bin/pint",
        "lint:fix": "vendor/bin/pint --repair"
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "php-http/discovery": true
        }
    }
}
```

## Standards à Respecter (Référence: laravel.md)

### Typage Strict PHP 8.4

Tous les fichiers PHP doivent commencer par :

```php
<?php

declare(strict_types=1);
```

### Classes Finales et Readonly

- Utiliser `final` par défaut pour les classes
- Utiliser `readonly` pour les propriétés immutables et DTOs
- Utiliser les types union et intersection PHP 8.4

### Injection de Dépendances

- Coder contre des interfaces, pas des implémentations
- Utiliser le constructor injection avec `private readonly`
- Enregistrer les bindings dans `AppServiceProvider`

## Dépendances

- **Prérequis** : Phase 1 complétée (infrastructure AWS via Terraform)
- **Utilisé par** : Toutes les tâches de la Phase 2

## Checklist

- [x] Créer le projet Laravel 12
- [x] Installer Bref et bref/laravel-bridge
- [x] Installer aws/aws-sdk-php-laravel
- [x] Installer jaggedsoft/php-binance-api
- [x] Installer les packages de dev (pint)
- [x] Créer la structure des dossiers complète
- [x] Créer config/bot.php
- [x] Créer config/dynamodb.php
- [x] Configurer config/services.php (Binance, Telegram)
- [x] Configurer .env.example
- [x] Ajouter declare(strict_types=1) dans bootstrap/app.php
- [x] Optimiser la config pour Lambda (logging stderr)
- [x] Mettre à jour .gitignore
- [x] Créer les fichiers .gitkeep
- [x] Vérifier avec `php artisan serve`
- [x] Vérifier avec `vendor/bin/pint --test`
- [x] Commit initial du projet Laravel
