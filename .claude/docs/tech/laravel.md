# Laravel 12 - Standards et Bonnes Pratiques

Ce document définit les conventions et bonnes pratiques à appliquer pour le développement du Trading Bot avec Laravel 12.

## Structure du Projet

```
app/
├── Console/
│   └── Commands/           # Commandes Artisan (bot:run, report:daily)
├── Contracts/              # Interfaces des services
├── DTOs/                   # Data Transfer Objects (immutables)
├── Enums/                  # Énumérations PHP 8.4
├── Events/                 # Événements applicatifs
├── Exceptions/             # Exceptions personnalisées par domaine
├── Http/
│   ├── Controllers/        # Controllers (légers, délèguent aux services)
│   ├── Middleware/
│   └── Requests/           # Form Requests (validation)
├── Jobs/                   # Jobs asynchrones (SQS)
├── Listeners/              # Listeners d'événements
├── Models/                 # Modèles DynamoDB
├── Providers/              # Service Providers
├── Repositories/           # Accès aux données DynamoDB
└── Services/               # Logique métier
    ├── Binance/
    │   ├── BinanceService.php
    │   └── Contracts/
    │       └── BinanceServiceInterface.php
    ├── Notification/
    │   ├── TelegramService.php
    │   └── SnsService.php
    └── Trading/
        ├── TradingService.php
        ├── Indicators/
        │   ├── RsiIndicator.php
        │   └── MovingAverageIndicator.php
        └── Strategies/
            ├── RsiStrategy.php
            └── MovingAverageStrategy.php

config/
├── bot.php                 # Configuration du bot
├── services.php            # Services externes (Binance, Telegram)
└── dynamodb.php            # Configuration DynamoDB
```

## Conventions de Nommage

### Classes

| Type | Convention | Exemple |
|------|------------|---------|
| Controller | PascalCase + `Controller` | `DashboardController` |
| Model | PascalCase, singulier | `Trade` |
| Service | PascalCase + `Service` | `BinanceService` |
| Repository | PascalCase + `Repository` | `TradeRepository` |
| Job | PascalCase, verbe d'action | `ProcessOrder` |
| Event | PascalCase, passé composé | `TradeExecuted` |
| Listener | PascalCase, verbe d'action | `SendTradeNotification` |
| Request | PascalCase + `Request` | `ExecuteTradeRequest` |
| Exception | PascalCase + `Exception` | `BinanceApiException` |
| Enum | PascalCase | `OrderStatus` |
| Command | PascalCase | `RunBot` (fichier: `RunBot.php`) |
| DTO | PascalCase + `DTO` ou descriptif | `TradeResultDTO`, `DailyReport` |
| Interface | PascalCase + `Interface` | `BinanceServiceInterface` |
| Indicator | PascalCase + `Indicator` | `RsiIndicator` |
| Strategy | PascalCase + `Strategy` | `RsiStrategy` |

### Méthodes et Variables

| Type | Convention | Exemple |
|------|------------|---------|
| Méthodes publiques | camelCase, verbes d'action | `executeOrder()`, `calculateRsi()` |
| Méthodes privées | camelCase, préfixe descriptif | `prepareOrderData()`, `validateBalance()` |
| Variables | camelCase | `$currentPrice`, `$tradeAmount` |
| Constantes | SCREAMING_SNAKE_CASE | `MAX_RETRY_ATTEMPTS` |
| Propriétés | camelCase | `$binanceClient` |

### Fichiers

| Type | Convention | Exemple |
|------|------------|---------|
| Classes | PascalCase.php | `BinanceService.php` |
| Config | kebab-case.php | `trading-bot.php` |
| Views | kebab-case.blade.php | `trade-history.blade.php` |
| Migrations | snake_case avec timestamp | `2024_01_15_create_trades_table.php` |

## Typage Strict PHP 8.4

### Règles Obligatoires

```php
<?php

declare(strict_types=1);

namespace App\Services\Trading;

use App\DTOs\TradeResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderType;

final class TradingService
{
    public function __construct(
        private readonly BinanceServiceInterface $binance,
        private readonly TradeRepositoryInterface $tradeRepository,
    ) {}

    public function executeMarketOrder(
        string $symbol,
        OrderSide $side,
        float $amount,
    ): TradeResultDTO {
        // ...
    }
}
```

### Types PHP 8.4 à Utiliser

```php
// Types union
public function process(string|int $id): void {}

// Types intersection
public function handle(Arrayable&Countable $data): void {}

// Types nullable
public function find(string $id): ?Trade {}

// Types de retour never (fonctions qui ne retournent jamais)
public function fail(string $message): never
{
    throw new TradingException($message);
}

// Types de retour mixed (à éviter, préférer les types explicites)
// À utiliser uniquement si vraiment nécessaire
```

## Enums

### Définition des Enums

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSide: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';

    public function label(): string
    {
        return match($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
        };
    }

    public function isOpposite(self $other): bool
    {
        return $this !== $other;
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Filled = 'filled';
    case PartiallyFilled = 'partially_filled';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Error = 'error';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Filled,
            self::Cancelled,
            self::Rejected,
            self::Error,
        ]);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum Strategy: string
{
    case Rsi = 'rsi';
    case MovingAverage = 'ma';
    case Combined = 'combined';

    public function displayName(): string
    {
        return match($this) {
            self::Rsi => 'RSI (Relative Strength Index)',
            self::MovingAverage => 'Moyennes Mobiles (MA50/MA200)',
            self::Combined => 'RSI + Moyennes Mobiles',
        };
    }
}
```

### Validation avec Enums

```php
use App\Enums\Strategy;
use Illuminate\Validation\Rule;

$request->validate([
    'strategy' => ['required', Rule::enum(Strategy::class)],
    'side' => ['required', Rule::enum(OrderSide::class)],
]);

// Filtrer certaines valeurs
Rule::enum(OrderStatus::class)->only([
    OrderStatus::Pending,
    OrderStatus::Filled,
]);

// Exclure certaines valeurs
Rule::enum(OrderStatus::class)->except([
    OrderStatus::Error,
]);
```

## DTOs (Data Transfer Objects)

### Structure d'un DTO

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use DateTimeImmutable;

final readonly class TradeResultDTO
{
    public function __construct(
        public string $orderId,
        public string $symbol,
        public OrderSide $side,
        public OrderStatus $status,
        public float $quantity,
        public float $price,
        public float $quoteQuantity,
        public float $commission,
        public string $commissionAsset,
        public DateTimeImmutable $executedAt,
    ) {}

    public static function fromBinanceResponse(array $response): self
    {
        return new self(
            orderId: (string) $response['orderId'],
            symbol: $response['symbol'],
            side: OrderSide::from($response['side']),
            status: OrderStatus::from(strtolower($response['status'])),
            quantity: (float) $response['executedQty'],
            price: (float) $response['price'],
            quoteQuantity: (float) $response['cummulativeQuoteQty'],
            commission: self::calculateCommission($response['fills'] ?? []),
            commissionAsset: $response['fills'][0]['commissionAsset'] ?? 'USDT',
            executedAt: new DateTimeImmutable(),
        );
    }

    private static function calculateCommission(array $fills): float
    {
        return array_sum(array_column($fills, 'commission'));
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'symbol' => $this->symbol,
            'side' => $this->side->value,
            'status' => $this->status->value,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'quote_quantity' => $this->quoteQuantity,
            'commission' => $this->commission,
            'commission_asset' => $this->commissionAsset,
            'executed_at' => $this->executedAt->format('c'),
        ];
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class DailyReportDTO
{
    public function __construct(
        public DateTimeImmutable $date,
        public int $totalTrades,
        public int $buyCount,
        public int $sellCount,
        public float $totalPnl,
        public float $totalPnlPercent,
        public array $balances,
        public float $totalBalanceUsdt,
        /** @var TradeResultDTO[] */
        public array $trades,
    ) {}
}
```

## Services et Injection de Dépendances

### Interface de Service

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\TradeResultDTO;

interface BinanceServiceInterface
{
    public function getCurrentPrice(string $symbol): float;

    public function getAccountBalances(): array;

    public function getKlines(string $symbol, string $interval, int $limit): array;

    public function marketBuy(string $symbol, float $quoteAmount): TradeResultDTO;

    public function marketSell(string $symbol, float $quantity): TradeResultDTO;
}
```

### Implémentation de Service

```php
<?php

declare(strict_types=1);

namespace App\Services\Binance;

use App\Contracts\BinanceServiceInterface;
use App\DTOs\TradeResultDTO;
use App\Exceptions\BinanceApiException;
use App\Exceptions\InsufficientBalanceException;
use Illuminate\Support\Facades\Log;

final class BinanceService implements BinanceServiceInterface
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

    public function __construct(
        private readonly BinanceClient $client,
    ) {}

    public function getCurrentPrice(string $symbol): float
    {
        return $this->executeWithRetry(
            fn () => $this->client->getPrice($symbol)
        );
    }

    public function marketBuy(string $symbol, float $quoteAmount): TradeResultDTO
    {
        Log::info('Executing market buy order', [
            'symbol' => $symbol,
            'quote_amount' => $quoteAmount,
        ]);

        try {
            $response = $this->executeWithRetry(
                fn () => $this->client->marketBuy($symbol, $quoteAmount)
            );

            $result = TradeResultDTO::fromBinanceResponse($response);

            Log::info('Market buy order executed', $result->toArray());

            return $result;
        } catch (BinanceApiException $e) {
            Log::error('Market buy order failed', [
                'symbol' => $symbol,
                'quote_amount' => $quoteAmount,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function executeWithRetry(callable $operation): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $operation();
            } catch (BinanceApiException $e) {
                $lastException = $e;

                if (!$this->isRetryableError($e)) {
                    throw $e;
                }

                if ($attempt < self::MAX_RETRIES) {
                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
            }
        }

        throw $lastException;
    }

    private function isRetryableError(BinanceApiException $e): bool
    {
        return in_array($e->getCode(), [-1000, -1001, -1003], true);
    }
}
```

### Enregistrement dans le Service Provider

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\Repositories\DynamoDbTradeRepository;
use App\Services\Binance\BinanceService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public array $bindings = [
        BinanceServiceInterface::class => BinanceService::class,
        TradeRepositoryInterface::class => DynamoDbTradeRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(BinanceClient::class, function ($app) {
            return new BinanceClient(
                apiKey: config('services.binance.api_key'),
                apiSecret: config('services.binance.api_secret'),
                testnet: config('services.binance.testnet', true),
            );
        });
    }
}
```

## Commandes Artisan

### Structure d'une Commande

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\TradingServiceInterface;
use App\Enums\Strategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RunBot extends Command
{
    protected $signature = 'bot:run
        {--symbol=BTCUSDT : Trading pair symbol}
        {--strategy=rsi : Trading strategy (rsi, ma, combined)}
        {--dry-run : Execute without placing real orders}
        {--force : Run even if bot is disabled}';

    protected $description = 'Execute the trading bot strategy';

    public function __construct(
        private readonly TradingServiceInterface $tradingService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $symbol = $this->option('symbol');
        $strategy = Strategy::from($this->option('strategy'));
        $dryRun = $this->option('dry-run');

        Log::info('Bot execution started', [
            'symbol' => $symbol,
            'strategy' => $strategy->value,
            'dry_run' => $dryRun,
        ]);

        try {
            $result = $this->tradingService->executeStrategy(
                symbol: $symbol,
                strategy: $strategy,
                dryRun: $dryRun,
            );

            $this->info("Strategy executed: {$result->signal->value}");

            if ($result->trade !== null) {
                $this->info("Trade executed: {$result->trade->side->value} {$result->trade->quantity} @ {$result->trade->price}");
            }

            Log::info('Bot execution completed', [
                'signal' => $result->signal->value,
                'trade' => $result->trade?->toArray(),
            ]);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Bot execution failed: {$e->getMessage()}");

            Log::error('Bot execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            report($e);

            return Command::FAILURE;
        }
    }
}
```

## Gestion des Exceptions

### Exception Personnalisée

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class BinanceApiException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?array $context = null,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context ?? [];
    }

    public function report(): void
    {
        // Logique de reporting personnalisée si nécessaire
    }
}
```

### Configuration des Exceptions (bootstrap/app.php)

```php
->withExceptions(function (Exceptions $exceptions): void {
    // Contexte global pour tous les logs d'exception
    $exceptions->context(fn () => [
        'environment' => config('app.env'),
        'bot_enabled' => config('bot.enabled'),
    ]);

    // Niveau de log personnalisé pour certaines exceptions
    $exceptions->level(
        BinanceApiException::class,
        LogLevel::ERROR
    );

    // Reporting personnalisé
    $exceptions->report(function (BinanceApiException $e) {
        // Envoyer une notification SNS pour les erreurs critiques
        if ($e->getCode() === -2014) { // Invalid API key
            app(SnsService::class)->publishError($e);
            return false; // Stop la propagation au logger par défaut
        }
    });
})
```

## Jobs et Queues (SQS)

### Structure d'un Job

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\BinanceServiceInterface;
use App\DTOs\TradeResultDTO;
use App\Models\Trade;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 30;

    public function __construct(
        public readonly string $symbol,
        public readonly string $side,
        public readonly float $amount,
        public readonly string $idempotencyKey,
    ) {
        $this->onQueue('orders');
        $this->onConnection('sqs');
    }

    public function handle(BinanceServiceInterface $binance): void
    {
        Log::info('Processing order', [
            'symbol' => $this->symbol,
            'side' => $this->side,
            'amount' => $this->amount,
        ]);

        // Job idempotent: vérifier si déjà traité
        if (Trade::where('idempotency_key', $this->idempotencyKey)->exists()) {
            Log::info('Order already processed, skipping', [
                'idempotency_key' => $this->idempotencyKey,
            ]);
            return;
        }

        // Exécuter l'ordre
        $result = match ($this->side) {
            'BUY' => $binance->marketBuy($this->symbol, $this->amount),
            'SELL' => $binance->marketSell($this->symbol, $this->amount),
        };

        // Sauvegarder le trade
        Trade::create([
            ...$result->toArray(),
            'idempotency_key' => $this->idempotencyKey,
        ]);
    }

    /**
     * ID de déduplication pour SQS FIFO
     */
    public function deduplicationId(): string
    {
        return $this->idempotencyKey;
    }

    /**
     * Groupe de messages pour SQS FIFO
     */
    public function messageGroup(): string
    {
        return "orders-{$this->symbol}";
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Order processing failed', [
            'symbol' => $this->symbol,
            'side' => $this->side,
            'amount' => $this->amount,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Dispatch de Jobs

```php
// Dispatch simple
ProcessOrder::dispatch($symbol, $side, $amount, $idempotencyKey);

// Dispatch avec délai (max 15 min sur SQS)
ProcessOrder::dispatch($symbol, $side, $amount, $idempotencyKey)
    ->delay(now()->addMinutes(5));

// Dispatch sur une connexion/queue spécifique
ProcessOrder::dispatch($symbol, $side, $amount, $idempotencyKey)
    ->onConnection('sqs')
    ->onQueue('high-priority');

// Chain de jobs
Bus::chain([
    new AnalyzeMarket($symbol),
    new ExecuteTrade($symbol, $side, $amount),
    new SendNotification($symbol),
])->onQueue('trading')->dispatch();
```

## Logging

### Configuration (config/logging.php)

```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['stderr', 'cloudwatch'],
        'ignore_exceptions' => false,
    ],

    'stderr' => [
        'driver' => 'monolog',
        'level' => env('LOG_LEVEL', 'debug'),
        'handler' => StreamHandler::class,
        'formatter' => env('LOG_STDERR_FORMATTER'),
        'with' => [
            'stream' => 'php://stderr',
        ],
    ],
],
```

### Utilisation du Logging

```php
use Illuminate\Support\Facades\Log;

// Info avec contexte structuré
Log::info('Trade executed', [
    'symbol' => $trade->symbol,
    'side' => $trade->side->value,
    'quantity' => $trade->quantity,
    'price' => $trade->price,
    'pnl' => $trade->pnl,
]);

// Warning pour situations inhabituelles
Log::warning('Low balance detected', [
    'asset' => 'USDT',
    'balance' => $balance,
    'threshold' => $threshold,
]);

// Error avec exception
Log::error('API call failed', [
    'endpoint' => '/api/v3/order',
    'error' => $exception->getMessage(),
    'code' => $exception->getCode(),
    'context' => $exception->context(),
]);

// Channel spécifique
Log::channel('trading')->info('Signal detected', [
    'strategy' => $strategy->value,
    'signal' => $signal->value,
    'indicators' => $indicators,
]);
```

## Tests

### Tests Unitaires

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Trading\Indicators;

use App\Services\Trading\Indicators\RsiIndicator;
use PHPUnit\Framework\TestCase;

final class RsiIndicatorTest extends TestCase
{
    private RsiIndicator $indicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indicator = new RsiIndicator(period: 14);
    }

    public function test_calculate_rsi_with_upward_trend(): void
    {
        $prices = [44, 44.5, 45, 45.5, 46, 46.5, 47, 47.5, 48, 48.5, 49, 49.5, 50, 50.5, 51];

        $rsi = $this->indicator->calculate($prices);

        $this->assertGreaterThan(70, $rsi);
    }

    public function test_calculate_rsi_with_downward_trend(): void
    {
        $prices = [51, 50.5, 50, 49.5, 49, 48.5, 48, 47.5, 47, 46.5, 46, 45.5, 45, 44.5, 44];

        $rsi = $this->indicator->calculate($prices);

        $this->assertLessThan(30, $rsi);
    }

    public function test_throws_exception_with_insufficient_data(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->indicator->calculate([1, 2, 3]);
    }
}
```

### Tests d'Intégration avec Mocking

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Contracts\BinanceServiceInterface;
use App\DTOs\TradeResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Services\Trading\TradingService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class TradingServiceTest extends TestCase
{
    public function test_executes_buy_order_when_rsi_below_threshold(): void
    {
        // Arrange
        $this->instance(
            BinanceServiceInterface::class,
            Mockery::mock(BinanceServiceInterface::class, function (MockInterface $mock) {
                $mock->expects('getCurrentPrice')
                    ->with('BTCUSDT')
                    ->andReturn(42500.0);

                $mock->expects('getKlines')
                    ->andReturn($this->generateDowntrendKlines());

                $mock->expects('marketBuy')
                    ->with('BTCUSDT', 100.0)
                    ->andReturn(new TradeResultDTO(
                        orderId: '123456',
                        symbol: 'BTCUSDT',
                        side: OrderSide::Buy,
                        status: OrderStatus::Filled,
                        quantity: 0.00235,
                        price: 42500.0,
                        quoteQuantity: 100.0,
                        commission: 0.1,
                        commissionAsset: 'USDT',
                        executedAt: new DateTimeImmutable(),
                    ));
            })
        );

        // Act
        $service = app(TradingService::class);
        $result = $service->executeStrategy('BTCUSDT', Strategy::Rsi, dryRun: false);

        // Assert
        $this->assertEquals(Signal::Buy, $result->signal);
        $this->assertNotNull($result->trade);
        $this->assertEquals(OrderSide::Buy, $result->trade->side);
    }

    private function generateDowntrendKlines(): array
    {
        // Générer des données simulant une tendance baissière
        // ...
    }
}
```

### Tests de Commandes

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Contracts\TradingServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class RunBotCommandTest extends TestCase
{
    public function test_bot_run_command_executes_successfully(): void
    {
        $this->mock(TradingServiceInterface::class, function ($mock) {
            $mock->shouldReceive('executeStrategy')
                ->once()
                ->andReturn(new TradingResult(
                    signal: Signal::Hold,
                    trade: null,
                ));
        });

        $this->artisan('bot:run', ['--symbol' => 'BTCUSDT'])
            ->assertSuccessful()
            ->expectsOutput('Strategy executed: hold');
    }

    public function test_bot_run_command_handles_failure(): void
    {
        $this->mock(TradingServiceInterface::class, function ($mock) {
            $mock->shouldReceive('executeStrategy')
                ->once()
                ->andThrow(new \RuntimeException('API Error'));
        });

        $this->artisan('bot:run')
            ->assertFailed()
            ->expectsOutput('Bot execution failed: API Error');
    }
}
```

## Configuration

### Fichier config/bot.php

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
        'amount' => env('BOT_AMOUNT', 100),
        'strategy' => env('BOT_STRATEGY', 'rsi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | RSI Strategy Configuration
    |--------------------------------------------------------------------------
    */
    'rsi' => [
        'period' => env('BOT_RSI_PERIOD', 14),
        'oversold' => env('BOT_RSI_OVERSOLD', 30),
        'overbought' => env('BOT_RSI_OVERBOUGHT', 70),
    ],

    /*
    |--------------------------------------------------------------------------
    | Moving Average Strategy Configuration
    |--------------------------------------------------------------------------
    */
    'ma' => [
        'short_period' => env('BOT_MA_SHORT', 50),
        'long_period' => env('BOT_MA_LONG', 200),
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_trades_per_day' => env('BOT_MAX_TRADES_DAY', 50),
        'max_amount_per_trade' => env('BOT_MAX_AMOUNT_TRADE', 1000),
        'min_balance_usdt' => env('BOT_MIN_BALANCE', 100),
        'cooldown_minutes' => env('BOT_COOLDOWN_MINUTES', 5),
    ],
];
```

### Fichier config/services.php (section)

```php
'binance' => [
    'api_key' => env('BINANCE_API_KEY'),
    'api_secret' => env('BINANCE_API_SECRET'),
    'testnet' => env('BINANCE_TESTNET', true),
    'base_url' => env('BINANCE_TESTNET', true)
        ? 'https://testnet.binance.vision'
        : 'https://api.binance.com',
],

'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id' => env('TELEGRAM_CHAT_ID'),
    'enabled' => env('TELEGRAM_ENABLED', true),
],
```

## Configuration AWS Lambda (Bref)

### Packages Requis

```bash
composer require bref/bref bref/laravel-bridge aws/aws-sdk-php
```

### Optimisations Lambda

```env
# .env pour Lambda
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
LOG_LEVEL=info

# Cache et Session via DynamoDB
CACHE_DRIVER=dynamodb
SESSION_DRIVER=array

# Queue via SQS
QUEUE_CONNECTION=sqs
SQS_PREFIX=https://sqs.eu-west-3.amazonaws.com/ACCOUNT_ID
SQS_QUEUE=trading-bot-prod-sqs-orders

# DynamoDB
DYNAMODB_CACHE_TABLE=trading-bot-prod-cache
AWS_DEFAULT_REGION=eu-west-3
```

### Bootstrap Optimisé

```php
// bootstrap/app.php optimisations pour Lambda
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Pas de session/cookies pour les commandes Lambda
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Configuration exceptions
    })
    ->create();
```

## Commandes Artisan Utiles

```bash
# Développement
php artisan serve
php artisan tinker

# Cache (production/Lambda)
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Clear cache
php artisan config:clear
php artisan cache:clear

# Tests
php artisan test
php artisan test --filter=RsiIndicatorTest
php artisan test --coverage

# Génération
php artisan make:model Trade
php artisan make:controller DashboardController
php artisan make:command RunBot
php artisan make:job ProcessOrder
php artisan make:event TradeExecuted
php artisan make:enum OrderStatus
php artisan make:exception BinanceApiException

# Bot
php artisan bot:run
php artisan bot:run --dry-run
php artisan bot:run --symbol=ETHUSDT --strategy=ma

# Report
php artisan report:daily
php artisan report:daily --date=2024-12-05
```

## Règles Importantes

1. **Typage strict** : `declare(strict_types=1)` obligatoire dans tous les fichiers PHP
2. **Classes finales** : Utiliser `final` par défaut sauf si l'héritage est prévu
3. **Readonly** : Utiliser `readonly` pour les propriétés immutables et les DTOs
4. **Interfaces** : Toujours coder contre des interfaces, pas des implémentations
5. **Injection de dépendances** : Préférer le constructor injection
6. **Services légers** : Controllers délèguent aux services, pas de logique métier
7. **Idempotence** : Les Jobs doivent être idempotents et réexécutables
8. **Validation** : Utiliser les Form Requests pour toute validation
9. **Enums** : Utiliser les enums PHP 8.4 pour toutes les constantes typées
10. **Logging** : Logger avec contexte structuré, jamais de données sensibles
11. **Exceptions** : Exceptions personnalisées par domaine avec context()
12. **Tests** : Couvrir les services critiques et les indicateurs techniques
