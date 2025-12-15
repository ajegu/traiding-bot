# Task 2.5 - Models/Repositories DynamoDB (Trade, BotConfig, Report)

## Objectif

Créer les modèles et repositories pour interagir avec DynamoDB. Utiliser le pattern Repository pour abstraire l'accès aux données.

## Fichiers à créer

| Fichier | Description |
|---------|-------------|
| `app/Contracts/TradeRepositoryInterface.php` | Interface du repository Trade |
| `app/Contracts/BotConfigRepositoryInterface.php` | Interface du repository BotConfig |
| `app/Contracts/ReportRepositoryInterface.php` | Interface du repository Report |
| `app/Repositories/DynamoDbTradeRepository.php` | Implémentation DynamoDB Trade |
| `app/Repositories/DynamoDbBotConfigRepository.php` | Implémentation DynamoDB BotConfig |
| `app/Repositories/DynamoDbReportRepository.php` | Implémentation DynamoDB Report |
| `app/Models/Trade.php` | Modèle Trade |
| `app/Models/BotConfig.php` | Modèle BotConfig |
| `app/Models/Report.php` | Modèle Report |

## Design DynamoDB

### Structure Single Table (trades)

| pk | sk | Données |
|----|----|---------|
| `TRADE#{uuid}` | `METADATA` | Données complètes du trade |
| `SYMBOL#{symbol}` | `{timestamp}#{uuid}` | Index par symbole |
| `DATE#{YYYY-MM-DD}` | `{timestamp}#{uuid}` | Index par date |

### Structure Table bot_config

| pk | sk | Données |
|----|----|---------|
| `CONFIG#bot` | `SETTINGS` | Configuration générale |
| `CONFIG#bot` | `LAST_EXECUTION` | Dernière exécution |

### Structure Table reports

| pk | sk | Données |
|----|----|---------|
| `REPORT#{YYYY-MM-DD}` | `DAILY` | Rapport journalier |

## Implémentation

### 1. Interface TradeRepositoryInterface

**Créer** : `app/Contracts/TradeRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\TradeResultDTO;
use App\DTOs\TradeStatsDTO;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface TradeRepositoryInterface
{
    /**
     * Enregistre un nouveau trade.
     */
    public function save(TradeResultDTO $trade, string $strategy, array $indicators = []): string;

    /**
     * Récupère un trade par son ID.
     */
    public function findById(string $id): ?array;

    /**
     * Récupère les trades d'une date donnée.
     */
    public function findByDate(CarbonInterface $date, int $limit = 50): Collection;

    /**
     * Récupère les trades entre deux dates.
     */
    public function findByDateRange(CarbonInterface $from, CarbonInterface $to): Collection;

    /**
     * Récupère les trades d'un symbole.
     */
    public function findBySymbol(string $symbol, int $limit = 50): Collection;

    /**
     * Récupère les derniers trades.
     */
    public function findRecent(int $limit = 10): Collection;

    /**
     * Calcule les statistiques pour une période.
     */
    public function getStatsByPeriod(CarbonInterface $from, CarbonInterface $to): TradeStatsDTO;

    /**
     * Compte les trades d'une date.
     */
    public function countByDate(CarbonInterface $date): int;

    /**
     * Récupère les positions ouvertes (BUY sans SELL correspondant).
     */
    public function getOpenPositions(?string $symbol = null): Collection;

    /**
     * Met à jour le P&L d'un trade.
     */
    public function updatePnl(string $id, float $pnl, float $pnlPercent, ?string $relatedTradeId = null): void;
}
```

### 2. Implémentation DynamoDbTradeRepository

**Créer** : `app/Repositories/DynamoDbTradeRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TradeRepositoryInterface;
use App\DTOs\TradeResultDTO;
use App\DTOs\TradeStatsDTO;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class DynamoDbTradeRepository implements TradeRepositoryInterface
{
    private readonly Marshaler $marshaler;
    private readonly string $tableName;

    public function __construct(
        private readonly DynamoDbClient $client,
    ) {
        $this->marshaler = new Marshaler();
        $this->tableName = config('services.dynamodb.tables.trades');
    }

    public function save(TradeResultDTO $trade, string $strategy, array $indicators = []): string
    {
        $id = Str::uuid()->toString();
        $timestamp = $trade->executedAt->format('c');
        $dateKey = $trade->executedAt->format('Y-m-d');

        $item = [
            'pk' => "TRADE#{$id}",
            'sk' => 'METADATA',
            'id' => $id,
            'order_id' => $trade->orderId,
            'client_order_id' => $trade->clientOrderId,
            'symbol' => $trade->symbol,
            'side' => $trade->side->value,
            'type' => $trade->type->value,
            'status' => $trade->status->value,
            'quantity' => $trade->quantity,
            'price' => $trade->price,
            'quote_quantity' => $trade->quoteQuantity,
            'commission' => $trade->commission,
            'commission_asset' => $trade->commissionAsset,
            'strategy' => $strategy,
            'indicators' => $indicators,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
            // GSI keys
            'gsi1pk' => "SYMBOL#{$trade->symbol}",
            'gsi1sk' => "{$timestamp}#{$id}",
            'gsi2pk' => "DATE#{$dateKey}",
            'gsi2sk' => "{$timestamp}#{$id}",
        ];

        $this->client->putItem([
            'TableName' => $this->tableName,
            'Item' => $this->marshaler->marshalItem($item),
        ]);

        return $id;
    }

    public function findById(string $id): ?array
    {
        $result = $this->client->getItem([
            'TableName' => $this->tableName,
            'Key' => $this->marshaler->marshalItem([
                'pk' => "TRADE#{$id}",
                'sk' => 'METADATA',
            ]),
        ]);

        if (!isset($result['Item'])) {
            return null;
        }

        return $this->marshaler->unmarshalItem($result['Item']);
    }

    public function findByDate(CarbonInterface $date, int $limit = 50): Collection
    {
        $dateKey = $date->format('Y-m-d');

        $result = $this->client->query([
            'TableName' => $this->tableName,
            'IndexName' => 'gsi2-date-index',
            'KeyConditionExpression' => 'gsi2pk = :pk',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':pk' => "DATE#{$dateKey}",
            ]),
            'ScanIndexForward' => false,
            'Limit' => $limit,
        ]);

        return $this->unmarshalItems($result['Items'] ?? []);
    }

    public function findByDateRange(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $trades = collect();
        $current = $from->copy();

        while ($current->lte($to)) {
            $dayTrades = $this->findByDate($current, 1000);
            $trades = $trades->merge($dayTrades);
            $current->addDay();
        }

        return $trades->sortByDesc('created_at');
    }

    public function findBySymbol(string $symbol, int $limit = 50): Collection
    {
        $result = $this->client->query([
            'TableName' => $this->tableName,
            'IndexName' => 'gsi1-symbol-index',
            'KeyConditionExpression' => 'gsi1pk = :pk',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':pk' => "SYMBOL#{$symbol}",
            ]),
            'ScanIndexForward' => false,
            'Limit' => $limit,
        ]);

        return $this->unmarshalItems($result['Items'] ?? []);
    }

    public function findRecent(int $limit = 10): Collection
    {
        // Scan avec limit - pas optimal mais acceptable pour de petits volumes
        $result = $this->client->scan([
            'TableName' => $this->tableName,
            'FilterExpression' => 'sk = :sk',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':sk' => 'METADATA',
            ]),
            'Limit' => $limit * 10, // Over-fetch pour compenser le filtre
        ]);

        return $this->unmarshalItems($result['Items'] ?? [])
            ->sortByDesc('created_at')
            ->take($limit);
    }

    public function getStatsByPeriod(CarbonInterface $from, CarbonInterface $to): TradeStatsDTO
    {
        $trades = $this->findByDateRange($from, $to);

        return TradeStatsDTO::fromTrades($trades->toArray());
    }

    public function countByDate(CarbonInterface $date): int
    {
        $dateKey = $date->format('Y-m-d');

        $result = $this->client->query([
            'TableName' => $this->tableName,
            'IndexName' => 'gsi2-date-index',
            'KeyConditionExpression' => 'gsi2pk = :pk',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':pk' => "DATE#{$dateKey}",
            ]),
            'Select' => 'COUNT',
        ]);

        return $result['Count'] ?? 0;
    }

    public function getOpenPositions(?string $symbol = null): Collection
    {
        $trades = $symbol !== null
            ? $this->findBySymbol($symbol, 1000)
            : $this->findRecent(1000);

        return $trades->filter(function (array $trade) {
            return ($trade['side'] ?? '') === 'BUY'
                && !isset($trade['related_trade_id']);
        });
    }

    public function updatePnl(string $id, float $pnl, float $pnlPercent, ?string $relatedTradeId = null): void
    {
        $updateExpression = 'SET pnl = :pnl, pnl_percent = :pnlPercent, updated_at = :now';
        $expressionValues = [
            ':pnl' => $pnl,
            ':pnlPercent' => $pnlPercent,
            ':now' => now()->toIso8601String(),
        ];

        if ($relatedTradeId !== null) {
            $updateExpression .= ', related_trade_id = :relatedId';
            $expressionValues[':relatedId'] = $relatedTradeId;
        }

        $this->client->updateItem([
            'TableName' => $this->tableName,
            'Key' => $this->marshaler->marshalItem([
                'pk' => "TRADE#{$id}",
                'sk' => 'METADATA',
            ]),
            'UpdateExpression' => $updateExpression,
            'ExpressionAttributeValues' => $this->marshaler->marshalItem($expressionValues),
        ]);
    }

    private function unmarshalItems(array $items): Collection
    {
        return collect($items)->map(fn (array $item) => $this->marshaler->unmarshalItem($item));
    }
}
```

### 3. Interface BotConfigRepositoryInterface

**Créer** : `app/Contracts/BotConfigRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Enums\Strategy;
use Carbon\CarbonInterface;

interface BotConfigRepositoryInterface
{
    /**
     * Vérifie si le bot est activé.
     */
    public function isEnabled(): bool;

    /**
     * Active ou désactive le bot.
     */
    public function setEnabled(bool $enabled): void;

    /**
     * Récupère la stratégie active.
     */
    public function getStrategy(): Strategy;

    /**
     * Définit la stratégie active.
     */
    public function setStrategy(Strategy $strategy): void;

    /**
     * Récupère le symbole de trading.
     */
    public function getSymbol(): string;

    /**
     * Définit le symbole de trading.
     */
    public function setSymbol(string $symbol): void;

    /**
     * Récupère le montant par trade.
     */
    public function getAmount(): float;

    /**
     * Définit le montant par trade.
     */
    public function setAmount(float $amount): void;

    /**
     * Récupère la configuration complète.
     */
    public function getAll(): array;

    /**
     * Met à jour plusieurs paramètres.
     */
    public function update(array $settings): void;

    /**
     * Récupère la date de dernière exécution.
     */
    public function getLastExecution(): ?CarbonInterface;

    /**
     * Met à jour la date de dernière exécution.
     */
    public function setLastExecution(CarbonInterface $date): void;

    /**
     * Récupère le dernier signal détecté.
     */
    public function getLastSignal(): ?string;

    /**
     * Met à jour le dernier signal détecté.
     */
    public function setLastSignal(string $signal): void;
}
```

### 4. Implémentation DynamoDbBotConfigRepository

**Créer** : `app/Repositories/DynamoDbBotConfigRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\BotConfigRepositoryInterface;
use App\Enums\Strategy;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class DynamoDbBotConfigRepository implements BotConfigRepositoryInterface
{
    private const PK = 'CONFIG#bot';
    private const SK_SETTINGS = 'SETTINGS';
    private const SK_LAST_EXECUTION = 'LAST_EXECUTION';

    private readonly Marshaler $marshaler;
    private readonly string $tableName;
    private ?array $cachedSettings = null;

    public function __construct(
        private readonly DynamoDbClient $client,
    ) {
        $this->marshaler = new Marshaler();
        $this->tableName = config('services.dynamodb.tables.bot_config');
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->getSettings()['enabled'] ?? false);
    }

    public function setEnabled(bool $enabled): void
    {
        $this->updateSetting('enabled', $enabled);
    }

    public function getStrategy(): Strategy
    {
        $strategy = $this->getSettings()['strategy'] ?? 'rsi';

        return Strategy::from($strategy);
    }

    public function setStrategy(Strategy $strategy): void
    {
        $this->updateSetting('strategy', $strategy->value);
    }

    public function getSymbol(): string
    {
        return $this->getSettings()['symbol'] ?? config('bot.trading.symbol', 'BTCUSDT');
    }

    public function setSymbol(string $symbol): void
    {
        $this->updateSetting('symbol', $symbol);
    }

    public function getAmount(): float
    {
        return (float) ($this->getSettings()['amount'] ?? config('bot.trading.amount', 100));
    }

    public function setAmount(float $amount): void
    {
        $this->updateSetting('amount', $amount);
    }

    public function getAll(): array
    {
        $settings = $this->getSettings();
        $lastExecution = $this->getLastExecution();

        return [
            'enabled' => $settings['enabled'] ?? false,
            'strategy' => $settings['strategy'] ?? 'rsi',
            'symbol' => $settings['symbol'] ?? 'BTCUSDT',
            'amount' => $settings['amount'] ?? 100,
            'last_execution' => $lastExecution?->toIso8601String(),
            'last_signal' => $this->getLastSignal(),
        ];
    }

    public function update(array $settings): void
    {
        $current = $this->getSettings();
        $merged = array_merge($current, $settings);
        $merged['updated_at'] = now()->toIso8601String();

        $item = array_merge($merged, [
            'pk' => self::PK,
            'sk' => self::SK_SETTINGS,
        ]);

        $this->client->putItem([
            'TableName' => $this->tableName,
            'Item' => $this->marshaler->marshalItem($item),
        ]);

        $this->cachedSettings = null;
    }

    public function getLastExecution(): ?CarbonInterface
    {
        $result = $this->client->getItem([
            'TableName' => $this->tableName,
            'Key' => $this->marshaler->marshalItem([
                'pk' => self::PK,
                'sk' => self::SK_LAST_EXECUTION,
            ]),
        ]);

        if (!isset($result['Item'])) {
            return null;
        }

        $item = $this->marshaler->unmarshalItem($result['Item']);

        return isset($item['executed_at'])
            ? Carbon::parse($item['executed_at'])
            : null;
    }

    public function setLastExecution(CarbonInterface $date): void
    {
        $this->client->putItem([
            'TableName' => $this->tableName,
            'Item' => $this->marshaler->marshalItem([
                'pk' => self::PK,
                'sk' => self::SK_LAST_EXECUTION,
                'executed_at' => $date->toIso8601String(),
            ]),
        ]);
    }

    public function getLastSignal(): ?string
    {
        return $this->getSettings()['last_signal'] ?? null;
    }

    public function setLastSignal(string $signal): void
    {
        $this->updateSetting('last_signal', $signal);
    }

    private function getSettings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $result = $this->client->getItem([
            'TableName' => $this->tableName,
            'Key' => $this->marshaler->marshalItem([
                'pk' => self::PK,
                'sk' => self::SK_SETTINGS,
            ]),
        ]);

        if (!isset($result['Item'])) {
            return $this->getDefaultSettings();
        }

        $this->cachedSettings = $this->marshaler->unmarshalItem($result['Item']);

        return $this->cachedSettings;
    }

    private function getDefaultSettings(): array
    {
        return [
            'enabled' => config('bot.enabled', false),
            'strategy' => config('bot.strategy.active', 'rsi'),
            'symbol' => config('bot.trading.symbol', 'BTCUSDT'),
            'amount' => config('bot.trading.amount', 100),
        ];
    }

    private function updateSetting(string $key, mixed $value): void
    {
        $this->client->updateItem([
            'TableName' => $this->tableName,
            'Key' => $this->marshaler->marshalItem([
                'pk' => self::PK,
                'sk' => self::SK_SETTINGS,
            ]),
            'UpdateExpression' => 'SET #key = :value, updated_at = :now',
            'ExpressionAttributeNames' => ['#key' => $key],
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':value' => $value,
                ':now' => now()->toIso8601String(),
            ]),
        ]);

        $this->cachedSettings = null;
    }
}
```

### 5. Interface ReportRepositoryInterface

**Créer** : `app/Contracts/ReportRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\DailyReportDTO;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface ReportRepositoryInterface
{
    /**
     * Sauvegarde un rapport quotidien.
     */
    public function saveDailyReport(DailyReportDTO $report, ?int $telegramMessageId = null): void;

    /**
     * Récupère un rapport par date.
     */
    public function findByDate(CarbonInterface $date): ?array;

    /**
     * Récupère les rapports d'un mois.
     */
    public function findByMonth(int $year, int $month): Collection;

    /**
     * Récupère le dernier rapport.
     */
    public function findLatest(): ?array;

    /**
     * Vérifie si un rapport existe pour une date.
     */
    public function existsForDate(CarbonInterface $date): bool;
}
```

### 6. Implémentation DynamoDbReportRepository

**Créer** : `app/Repositories/DynamoDbReportRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ReportRepositoryInterface;
use App\DTOs\DailyReportDTO;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class DynamoDbReportRepository implements ReportRepositoryInterface
{
    private readonly Marshaler $marshaler;
    private readonly string $tableName;

    public function __construct(
        private readonly DynamoDbClient $client,
    ) {
        $this->marshaler = new Marshaler();
        $this->tableName = config('services.dynamodb.tables.reports');
    }

    public function saveDailyReport(DailyReportDTO $report, ?int $telegramMessageId = null): void
    {
        $dateKey = $report->date->format('Y-m-d');

        $item = [
            'pk' => "REPORT#{$dateKey}",
            'sk' => 'DAILY',
            'date' => $dateKey,
            'stats' => $report->stats->toArray(),
            'total_trades' => $report->stats->totalTrades,
            'total_pnl' => $report->stats->totalPnl,
            'total_balance_usdt' => $report->totalBalanceUsdt,
            'previous_day_balance_usdt' => $report->previousDayBalanceUsdt,
            'daily_change_percent' => $report->dailyChangePercent(),
            'telegram_message_id' => $telegramMessageId,
            'created_at' => now()->toIso8601String(),
            // GSI pour requêtes par mois
            'gsi1pk' => "MONTH#{$report->date->format('Y-m')}",
            'gsi1sk' => $dateKey,
        ];

        $this->client->putItem([
            'TableName' => $this->tableName,
            'Item' => $this->marshaler->marshalItem($item),
        ]);
    }

    public function findByDate(CarbonInterface $date): ?array
    {
        $dateKey = $date->format('Y-m-d');

        $result = $this->client->getItem([
            'TableName' => $this->tableName,
            'Key' => $this->marshaler->marshalItem([
                'pk' => "REPORT#{$dateKey}",
                'sk' => 'DAILY',
            ]),
        ]);

        if (!isset($result['Item'])) {
            return null;
        }

        return $this->marshaler->unmarshalItem($result['Item']);
    }

    public function findByMonth(int $year, int $month): Collection
    {
        $monthKey = sprintf('%04d-%02d', $year, $month);

        $result = $this->client->query([
            'TableName' => $this->tableName,
            'IndexName' => 'gsi1-month-index',
            'KeyConditionExpression' => 'gsi1pk = :pk',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':pk' => "MONTH#{$monthKey}",
            ]),
            'ScanIndexForward' => false,
        ]);

        return collect($result['Items'] ?? [])
            ->map(fn (array $item) => $this->marshaler->unmarshalItem($item));
    }

    public function findLatest(): ?array
    {
        // Scan limité pour trouver le rapport le plus récent
        $result = $this->client->scan([
            'TableName' => $this->tableName,
            'FilterExpression' => 'sk = :sk',
            'ExpressionAttributeValues' => $this->marshaler->marshalItem([
                ':sk' => 'DAILY',
            ]),
            'Limit' => 30,
        ]);

        $items = collect($result['Items'] ?? [])
            ->map(fn (array $item) => $this->marshaler->unmarshalItem($item))
            ->sortByDesc('date');

        return $items->first();
    }

    public function existsForDate(CarbonInterface $date): bool
    {
        return $this->findByDate($date) !== null;
    }
}
```

### 7. Enregistrement dans le Service Provider

**Modifier** : `app/Providers/AppServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BotConfigRepositoryInterface;
use App\Contracts\ReportRepositoryInterface;
use App\Contracts\TradeRepositoryInterface;
use App\Repositories\DynamoDbBotConfigRepository;
use App\Repositories\DynamoDbReportRepository;
use App\Repositories\DynamoDbTradeRepository;
use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public array $bindings = [
        TradeRepositoryInterface::class => DynamoDbTradeRepository::class,
        BotConfigRepositoryInterface::class => DynamoDbBotConfigRepository::class,
        ReportRepositoryInterface::class => DynamoDbReportRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(DynamoDbClient::class, function ($app) {
            $config = [
                'region' => config('services.aws.region', 'eu-west-3'),
                'version' => 'latest',
            ];

            // Credentials uniquement en local (Lambda utilise le rôle IAM)
            if (config('services.aws.credentials.key')) {
                $config['credentials'] = [
                    'key' => config('services.aws.credentials.key'),
                    'secret' => config('services.aws.credentials.secret'),
                ];
            }

            return new DynamoDbClient($config);
        });
    }

    public function boot(): void
    {
        //
    }
}
```

## Tests

**Créer** : `tests/Feature/Repositories/DynamoDbTradeRepositoryTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Contracts\TradeRepositoryInterface;
use App\DTOs\TradeResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DynamoDbTradeRepositoryTest extends TestCase
{
    // Note: Ces tests nécessitent DynamoDB Local ou sont des tests d'intégration

    public function test_save_and_find_trade(): void
    {
        $this->markTestSkipped('Requires DynamoDB Local setup');

        $repository = app(TradeRepositoryInterface::class);

        $trade = new TradeResultDTO(
            orderId: '12345',
            clientOrderId: 'test123',
            symbol: 'BTCUSDT',
            side: OrderSide::Buy,
            type: OrderType::Market,
            status: OrderStatus::Filled,
            quantity: 0.001,
            price: 42500.0,
            quoteQuantity: 42.50,
            commission: 0.04,
            commissionAsset: 'USDT',
            executedAt: new \DateTimeImmutable(),
        );

        $id = $repository->save($trade, 'rsi', ['rsi' => 28.5]);
        $found = $repository->findById($id);

        $this->assertNotNull($found);
        $this->assertEquals('BTCUSDT', $found['symbol']);
        $this->assertEquals('BUY', $found['side']);
    }
}
```

## Dépendances

- **Prérequis** : Tâche 2.3 (Enums), Tâche 2.4 (DTOs), Tâche 2.2 (Configuration)
- **Infrastructure** : Tables DynamoDB créées (Phase 1)
- **Utilisé par** : Tâches 2.6 (BinanceService), 2.8 (TradingStrategy), 2.11 (ReportService)

## Checklist

- [ ] Créer `app/Contracts/TradeRepositoryInterface.php`
- [ ] Créer `app/Contracts/BotConfigRepositoryInterface.php`
- [ ] Créer `app/Contracts/ReportRepositoryInterface.php`
- [ ] Créer `app/Repositories/DynamoDbTradeRepository.php`
- [ ] Créer `app/Repositories/DynamoDbBotConfigRepository.php`
- [ ] Créer `app/Repositories/DynamoDbReportRepository.php`
- [ ] Modifier `app/Providers/AppServiceProvider.php` (bindings + DynamoDbClient)
- [ ] Créer les tests d'intégration
- [ ] Tester avec DynamoDB local ou AWS
- [ ] Vérifier avec `vendor/bin/pint`
