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
│   ├── Console/Commands/
│   ├── Enums/
│   ├── DTOs/
│   ├── Models/
│   ├── Repositories/
│   ├── Services/
│   │   ├── Binance/
│   │   ├── Trading/
│   │   └── Notification/
│   └── Http/Controllers/
├── config/
│   ├── bot.php
│   └── services.php
├── bootstrap/
├── database/
├── public/
├── resources/views/
├── routes/
├── storage/
├── tests/
├── terraform/         # Déjà existant
├── .claude/           # Déjà existant
├── serverless.yml     # Config Bref
├── composer.json
└── .env.example
```

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
composer require --dev phpunit/phpunit laravel/pint
```

### Étape 6 : Créer la structure des dossiers

```bash
mkdir -p app/Console/Commands
mkdir -p app/Enums
mkdir -p app/DTOs
mkdir -p app/Models
mkdir -p app/Repositories
mkdir -p app/Services/Binance
mkdir -p app/Services/Trading
mkdir -p app/Services/Notification
mkdir -p app/Http/Controllers
mkdir -p app/Http/Requests
```

### Étape 7 : Configurer serverless.yml

**Créer** : `serverless.yml`

```yaml
service: trading-bot

provider:
  name: aws
  region: eu-west-3
  runtime: provided.al2
  stage: ${opt:stage, 'dev'}
  environment:
    APP_ENV: ${self:provider.stage}
    APP_DEBUG: ${self:custom.debug.${self:provider.stage}, 'false'}
    LOG_CHANNEL: stderr
    CACHE_DRIVER: array
    SESSION_DRIVER: array
    VIEW_COMPILED_PATH: /tmp/storage/framework/views

plugins:
  - ./vendor/bref/bref

custom:
  debug:
    dev: 'true'
    staging: 'false'
    prod: 'false'

functions:
  # Bot Executor (scheduled every 5 minutes)
  bot-executor:
    handler: artisan
    description: "Trading bot executor"
    timeout: 30
    memorySize: 512
    layers:
      - ${bref:layer.php-84}
      - ${bref:layer.console}
    events:
      - schedule:
          rate: rate(5 minutes)
          enabled: false  # Activer manuellement
          input:
            command: "bot:run"

  # Daily Report (scheduled daily at 8am UTC)
  daily-report:
    handler: artisan
    description: "Daily report generator"
    timeout: 60
    memorySize: 512
    layers:
      - ${bref:layer.php-84}
      - ${bref:layer.console}
    events:
      - schedule:
          rate: cron(0 8 * * ? *)
          enabled: false
          input:
            command: "report:daily"

  # Web Dashboard (API Gateway)
  web:
    handler: public/index.php
    description: "Web dashboard"
    timeout: 28
    memorySize: 1024
    layers:
      - ${bref:layer.php-84-fpm}
    events:
      - httpApi: '*'

package:
  patterns:
    - '!node_modules/**'
    - '!terraform/**'
    - '!tests/**'
    - '!.git/**'
    - '!.claude/**'
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
```

### Étape 9 : Optimisations pour Lambda

**Modifier** : `bootstrap/app.php`

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
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

### Étape 10 : Configurer .gitignore

**Modifier** : `.gitignore` (ajouter)

```gitignore
# Lambda
.serverless/
.bref/

# IDE
.idea/
.vscode/
*.swp

# Environment
.env
.env.local
.env.*.local

# Terraform
terraform/.terraform/
terraform/*.tfstate*
terraform/*.tfvars

# Vendor
/vendor/
/node_modules/

# Storage
/storage/*.key

# Cache
.phpunit.result.cache
```

## Vérification

```bash
# Vérifier l'installation Laravel
php artisan --version

# Vérifier Bref
vendor/bin/bref --version

# Lister les commandes disponibles
php artisan list

# Tester le serveur local
php artisan serve

# Vérifier la configuration
php artisan config:show app
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
        "lint": "vendor/bin/pint"
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

## Dépendances

- **Prérequis** : Phase 1 complétée (infrastructure AWS)
- **Utilisé par** : Toutes les tâches de la Phase 2

## Checklist

- [ ] Créer le projet Laravel 12
- [ ] Installer Bref et bref/laravel-bridge
- [ ] Installer aws/aws-sdk-php-laravel
- [ ] Installer jaggedsoft/php-binance-api
- [ ] Installer les packages de dev (phpunit, pint)
- [ ] Créer la structure des dossiers (Enums, DTOs, Services, etc.)
- [ ] Créer serverless.yml
- [ ] Configurer .env.example
- [ ] Optimiser la config pour Lambda (logging stderr)
- [ ] Mettre à jour .gitignore
- [ ] Vérifier avec `php artisan serve`
- [ ] Commit initial du projet Laravel
