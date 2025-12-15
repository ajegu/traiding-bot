# Task 2.12 - Commande bot:run

## Objectif

Créer la commande Artisan `bot:run` qui exécute la stratégie de trading configurée. Cette commande est le point d'entrée principal du bot, déclenchée toutes les 5 minutes par EventBridge.

## Dépendances

- **Task 2.2** : Configuration (config/bot.php)
- **Task 2.5** : Repositories (BotConfigRepository)
- **Task 2.6** : BinanceService
- **Task 2.8** : TradingStrategy
- **Task 2.9** : NotificationService

## Fichiers à créer

```
app/
└── Console/
    └── Commands/
        └── RunBot.php
```

## Implémentation

### app/Console/Commands/RunBot.php

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\BotConfigRepositoryInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\DTOs\TradingResultDTO;
use App\Enums\Signal;
use App\Enums\Strategy;
use App\Exceptions\BinanceApiException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Trade;
use App\Services\Trading\TradingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RunBot extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bot:run
        {--symbol= : Trading pair symbol (default: from config)}
        {--strategy= : Trading strategy: rsi, ma, combined (default: from config)}
        {--amount= : Trade amount in USDT (default: from config)}
        {--dry-run : Execute analysis without placing real orders}
        {--force : Run even if bot is disabled in config}';

    /**
     * The console command description.
     */
    protected $description = 'Execute the trading bot strategy analysis and place orders if signals are detected';

    /**
     * Cooldown period in minutes between executions.
     */
    private const COOLDOWN_MINUTES = 4;

    public function __construct(
        private readonly BotConfigRepositoryInterface $configRepository,
        private readonly BinanceServiceInterface $binanceService,
        private readonly TradingService $tradingService,
        private readonly TradeRepositoryInterface $tradeRepository,
        private readonly NotificationServiceInterface $notificationService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        try {
            // Récupérer la configuration
            $config = $this->configRepository->get();

            // Vérifier si le bot est activé
            if (!$config->enabled && !$this->option('force')) {
                $this->info('Bot is disabled. Use --force to run anyway.');
                Log::info('Bot execution skipped: bot is disabled');
                return Command::SUCCESS;
            }

            // Vérifier le cooldown (éviter les doublons d'exécution)
            if (!$this->option('force') && $this->isInCooldown($config->lastExecution)) {
                $this->warn('Bot is in cooldown period. Use --force to bypass.');
                Log::info('Bot execution skipped: cooldown period active', [
                    'last_execution' => $config->lastExecution?->toIso8601String(),
                ]);
                return Command::SUCCESS;
            }

            // Déterminer les paramètres d'exécution
            $symbol = $this->option('symbol') ?? $config->symbol;
            $strategy = $this->resolveStrategy($this->option('strategy') ?? $config->strategy);
            $amount = (float) ($this->option('amount') ?? $config->amount);
            $dryRun = (bool) $this->option('dry-run');

            Log::info('Bot execution started', [
                'symbol' => $symbol,
                'strategy' => $strategy->value,
                'amount' => $amount,
                'dry_run' => $dryRun,
            ]);

            $this->info("Starting bot execution...");
            $this->table(
                ['Parameter', 'Value'],
                [
                    ['Symbol', $symbol],
                    ['Strategy', $strategy->displayName()],
                    ['Amount', "{$amount} USDT"],
                    ['Dry Run', $dryRun ? 'Yes' : 'No'],
                ]
            );

            // Exécuter la stratégie
            $result = $this->tradingService->executeStrategy(
                symbol: $symbol,
                strategy: $strategy,
                amount: $amount,
                dryRun: $dryRun,
            );

            // Afficher les résultats
            $this->displayResults($result);

            // Mettre à jour la configuration
            $this->configRepository->updateLastExecution(
                Carbon::now(),
                $result->signal
            );

            // Envoyer une notification si un trade a été exécuté
            if ($result->trade !== null && !$dryRun) {
                $this->notifyTradeExecuted($result);
            }

            $executionTime = round((microtime(true) - $startTime) * 1000);

            Log::info('Bot execution completed', [
                'signal' => $result->signal->value,
                'trade_executed' => $result->trade !== null,
                'execution_time_ms' => $executionTime,
            ]);

            $this->newLine();
            $this->info("Execution completed in {$executionTime}ms");

            return Command::SUCCESS;

        } catch (InsufficientBalanceException $e) {
            $this->handleInsufficientBalance($e);
            return Command::SUCCESS; // Not a failure, just skip

        } catch (BinanceApiException $e) {
            $this->handleBinanceError($e);
            return Command::FAILURE;

        } catch (Throwable $e) {
            $this->handleUnexpectedError($e);
            return Command::FAILURE;
        }
    }

    /**
     * Check if the bot is in cooldown period.
     */
    private function isInCooldown(?Carbon $lastExecution): bool
    {
        if ($lastExecution === null) {
            return false;
        }

        $cooldownEnd = $lastExecution->copy()->addMinutes(self::COOLDOWN_MINUTES);

        return Carbon::now()->isBefore($cooldownEnd);
    }

    /**
     * Resolve strategy from string input.
     */
    private function resolveStrategy(string $strategyValue): Strategy
    {
        return Strategy::from($strategyValue);
    }

    /**
     * Display trading results.
     */
    private function displayResults(TradingResultDTO $result): void
    {
        $this->newLine();

        // Signal
        $signalColor = match ($result->signal) {
            Signal::Buy => 'green',
            Signal::Sell => 'red',
            Signal::Hold => 'yellow',
        };

        $this->line("Signal: <fg={$signalColor};options=bold>{$result->signal->label()}</>");

        // Indicators
        $this->newLine();
        $this->info('Indicators:');
        $this->table(
            ['Indicator', 'Value'],
            [
                ['RSI', $result->indicators->rsi !== null ? round($result->indicators->rsi, 2) : 'N/A'],
                ['MA50', $result->indicators->ma50 !== null ? number_format($result->indicators->ma50, 2) : 'N/A'],
                ['MA200', $result->indicators->ma200 !== null ? number_format($result->indicators->ma200, 2) : 'N/A'],
                ['Current Price', number_format($result->indicators->currentPrice, 2) . ' USDT'],
            ]
        );

        // Trade details if executed
        if ($result->trade !== null) {
            $this->newLine();
            $this->info('Trade Executed:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Order ID', $result->trade->orderId],
                    ['Side', $result->trade->side->value],
                    ['Quantity', $result->trade->quantity],
                    ['Price', number_format($result->trade->price, 2) . ' USDT'],
                    ['Total', number_format($result->trade->quoteQuantity, 2) . ' USDT'],
                    ['Commission', $result->trade->commission . ' ' . $result->trade->commissionAsset],
                ]
            );
        } else {
            $this->newLine();
            $this->comment('No trade executed.');
        }
    }

    /**
     * Send notification for executed trade.
     */
    private function notifyTradeExecuted(TradingResultDTO $result): void
    {
        try {
            // Créer le modèle Trade pour la notification
            $trade = new Trade([
                'order_id' => $result->trade->orderId,
                'symbol' => $result->trade->symbol,
                'side' => $result->trade->side->value,
                'type' => $result->trade->type ?? 'MARKET',
                'quantity' => $result->trade->quantity,
                'price' => $result->trade->price,
                'quote_quantity' => $result->trade->quoteQuantity,
                'commission' => $result->trade->commission,
                'commission_asset' => $result->trade->commissionAsset,
                'status' => $result->trade->status->value,
                'strategy' => $result->strategy->value,
                'indicators' => $result->indicators->toArray(),
            ]);

            $this->notificationService->notifyTradeExecuted($trade);

            $this->info('Notification sent successfully.');

        } catch (Throwable $e) {
            // Ne pas faire échouer l'exécution pour une erreur de notification
            Log::warning('Failed to send trade notification', [
                'error' => $e->getMessage(),
            ]);
            $this->warn('Failed to send notification: ' . $e->getMessage());
        }
    }

    /**
     * Handle insufficient balance error.
     */
    private function handleInsufficientBalance(InsufficientBalanceException $e): void
    {
        $this->warn("Insufficient balance: {$e->getMessage()}");

        Log::warning('Bot execution skipped: insufficient balance', [
            'asset' => $e->asset,
            'available' => $e->available,
            'required' => $e->required,
        ]);
    }

    /**
     * Handle Binance API error.
     */
    private function handleBinanceError(BinanceApiException $e): void
    {
        $this->error("Binance API error: {$e->getMessage()} (code: {$e->getCode()})");

        Log::error('Bot execution failed: Binance API error', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'context' => $e->context(),
        ]);

        // Notifier l'erreur critique
        try {
            $this->notificationService->notifyError(
                'BINANCE_API_ERROR',
                $e->getMessage(),
                array_merge($e->context() ?? [], ['code' => $e->getCode()])
            );
        } catch (Throwable) {
            // Ignore notification errors
        }

        report($e);
    }

    /**
     * Handle unexpected error.
     */
    private function handleUnexpectedError(Throwable $e): void
    {
        $this->error("Unexpected error: {$e->getMessage()}");

        Log::error('Bot execution failed: unexpected error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Notifier l'erreur critique
        try {
            $this->notificationService->notifyCriticalError($e);
        } catch (Throwable) {
            // Ignore notification errors
        }

        report($e);
    }
}
```

## Tests

### tests/Feature/Console/RunBotCommandTest.php

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\BotConfigRepositoryInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\DTOs\IndicatorsDTO;
use App\DTOs\TradeResultDTO;
use App\DTOs\TradingResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Signal;
use App\Enums\Strategy;
use App\Exceptions\BinanceApiException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\BotConfig;
use App\Services\Trading\TradingService;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class RunBotCommandTest extends TestCase
{
    private MockInterface $configRepository;
    private MockInterface $tradingService;
    private MockInterface $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configRepository = Mockery::mock(BotConfigRepositoryInterface::class);
        $this->tradingService = Mockery::mock(TradingService::class);
        $this->notificationService = Mockery::mock(NotificationServiceInterface::class);

        $this->app->instance(BotConfigRepositoryInterface::class, $this->configRepository);
        $this->app->instance(TradingService::class, $this->tradingService);
        $this->app->instance(NotificationServiceInterface::class, $this->notificationService);
    }

    public function test_bot_runs_successfully_with_hold_signal(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: true,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $result = new TradingResultDTO(
            signal: Signal::Hold,
            indicators: new IndicatorsDTO(
                rsi: 50.0,
                ma50: 42000.0,
                ma200: 41000.0,
                currentPrice: 42500.0,
            ),
            trade: null,
            strategy: Strategy::Rsi,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        $this->configRepository
            ->shouldReceive('updateLastExecution')
            ->once();

        $this->tradingService
            ->shouldReceive('executeStrategy')
            ->once()
            ->with('BTCUSDT', Strategy::Rsi, 100.0, false)
            ->andReturn($result);

        // Act & Assert
        $this->artisan('bot:run')
            ->assertSuccessful()
            ->expectsOutputToContain('Signal: Hold');
    }

    public function test_bot_runs_with_buy_signal_and_executes_trade(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: true,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $tradeResult = new TradeResultDTO(
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
        );

        $result = new TradingResultDTO(
            signal: Signal::Buy,
            indicators: new IndicatorsDTO(
                rsi: 25.0,
                ma50: 42000.0,
                ma200: 41000.0,
                currentPrice: 42500.0,
            ),
            trade: $tradeResult,
            strategy: Strategy::Rsi,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        $this->configRepository
            ->shouldReceive('updateLastExecution')
            ->once();

        $this->tradingService
            ->shouldReceive('executeStrategy')
            ->once()
            ->andReturn($result);

        $this->notificationService
            ->shouldReceive('notifyTradeExecuted')
            ->once();

        // Act & Assert
        $this->artisan('bot:run')
            ->assertSuccessful()
            ->expectsOutputToContain('Signal: Buy')
            ->expectsOutputToContain('Trade Executed');
    }

    public function test_bot_skips_execution_when_disabled(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: false,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        // Act & Assert
        $this->artisan('bot:run')
            ->assertSuccessful()
            ->expectsOutputToContain('Bot is disabled');
    }

    public function test_bot_runs_when_disabled_with_force_option(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: false,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $result = new TradingResultDTO(
            signal: Signal::Hold,
            indicators: new IndicatorsDTO(
                rsi: 50.0,
                ma50: 42000.0,
                ma200: 41000.0,
                currentPrice: 42500.0,
            ),
            trade: null,
            strategy: Strategy::Rsi,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        $this->configRepository
            ->shouldReceive('updateLastExecution')
            ->once();

        $this->tradingService
            ->shouldReceive('executeStrategy')
            ->once()
            ->andReturn($result);

        // Act & Assert
        $this->artisan('bot:run', ['--force' => true])
            ->assertSuccessful();
    }

    public function test_bot_skips_execution_during_cooldown(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: true,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: Carbon::now()->subMinutes(2), // 2 minutes ago, cooldown is 4 minutes
            lastSignal: Signal::Hold,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        // Act & Assert
        $this->artisan('bot:run')
            ->assertSuccessful()
            ->expectsOutputToContain('cooldown');
    }

    public function test_bot_uses_command_options_over_config(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: true,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $result = new TradingResultDTO(
            signal: Signal::Hold,
            indicators: new IndicatorsDTO(
                rsi: null,
                ma50: 2500.0,
                ma200: 2400.0,
                currentPrice: 2600.0,
            ),
            trade: null,
            strategy: Strategy::MovingAverage,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        $this->configRepository
            ->shouldReceive('updateLastExecution')
            ->once();

        $this->tradingService
            ->shouldReceive('executeStrategy')
            ->once()
            ->with('ETHUSDT', Strategy::MovingAverage, 200.0, false)
            ->andReturn($result);

        // Act & Assert
        $this->artisan('bot:run', [
            '--symbol' => 'ETHUSDT',
            '--strategy' => 'ma',
            '--amount' => '200',
        ])->assertSuccessful();
    }

    public function test_dry_run_does_not_send_notifications(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: true,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $tradeResult = new TradeResultDTO(
            orderId: 'DRY_RUN_123',
            symbol: 'BTCUSDT',
            side: OrderSide::Buy,
            status: OrderStatus::Filled,
            quantity: 0.00235,
            price: 42500.0,
            quoteQuantity: 100.0,
            commission: 0.0,
            commissionAsset: 'USDT',
            executedAt: new DateTimeImmutable(),
        );

        $result = new TradingResultDTO(
            signal: Signal::Buy,
            indicators: new IndicatorsDTO(
                rsi: 25.0,
                ma50: 42000.0,
                ma200: 41000.0,
                currentPrice: 42500.0,
            ),
            trade: $tradeResult,
            strategy: Strategy::Rsi,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        $this->configRepository
            ->shouldReceive('updateLastExecution')
            ->once();

        $this->tradingService
            ->shouldReceive('executeStrategy')
            ->once()
            ->with('BTCUSDT', Strategy::Rsi, 100.0, true)
            ->andReturn($result);

        // notifyTradeExecuted should NOT be called in dry-run mode
        $this->notificationService
            ->shouldNotReceive('notifyTradeExecuted');

        // Act & Assert
        $this->artisan('bot:run', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_handles_insufficient_balance_gracefully(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: true,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        $this->tradingService
            ->shouldReceive('executeStrategy')
            ->once()
            ->andThrow(new InsufficientBalanceException('USDT', 50.0, 100.0));

        // Act & Assert
        $this->artisan('bot:run')
            ->assertSuccessful() // Should not fail
            ->expectsOutputToContain('Insufficient balance');
    }

    public function test_handles_binance_api_error(): void
    {
        // Arrange
        $config = new BotConfig(
            enabled: true,
            strategy: 'rsi',
            symbol: 'BTCUSDT',
            amount: 100.0,
            lastExecution: null,
            lastSignal: null,
        );

        $this->configRepository
            ->shouldReceive('get')
            ->once()
            ->andReturn($config);

        $this->tradingService
            ->shouldReceive('executeStrategy')
            ->once()
            ->andThrow(new BinanceApiException('Rate limit exceeded', -1003));

        $this->notificationService
            ->shouldReceive('notifyError')
            ->once();

        // Act & Assert
        $this->artisan('bot:run')
            ->assertFailed()
            ->expectsOutputToContain('Binance API error');
    }
}
```

## Enregistrement de la commande

La commande est automatiquement découverte par Laravel dans le dossier `app/Console/Commands/`.

Pour les versions antérieures ou si nécessaire, ajouter dans `routes/console.php` :

```php
// routes/console.php
// Les commandes sont auto-découvertes, pas besoin d'enregistrement manuel
```

## Usage

```bash
# Exécution normale (utilise la config)
php artisan bot:run

# Mode dry-run (analyse sans passer d'ordre)
php artisan bot:run --dry-run

# Forcer l'exécution même si bot désactivé
php artisan bot:run --force

# Override des paramètres
php artisan bot:run --symbol=ETHUSDT --strategy=ma --amount=200

# Combinaison d'options
php artisan bot:run --symbol=BTCUSDT --strategy=combined --dry-run

# Mode verbose
php artisan bot:run -v
```

## Configuration EventBridge

La commande est déclenchée par EventBridge via le handler Bref :

```hcl
# terraform/modules/lambda/main.tf
resource "aws_cloudwatch_event_rule" "bot_executor" {
  name                = "${var.name_prefix}-rule-bot-executor-5min"
  description         = "Execute trading bot every 5 minutes"
  schedule_expression = "rate(5 minutes)"
}

resource "aws_cloudwatch_event_target" "bot_executor" {
  rule      = aws_cloudwatch_event_rule.bot_executor.name
  target_id = "TradingBotExecutor"
  arn       = aws_lambda_function.artisan.arn
  input     = jsonencode({
    cli = "bot:run"
  })
}
```

## Checklist

- [ ] Créer `app/Console/Commands/RunBot.php`
- [ ] Tester la commande localement avec `--dry-run`
- [ ] Vérifier l'intégration avec TradingService
- [ ] Vérifier les notifications post-trade
- [ ] Tester la gestion des erreurs
- [ ] Tester le cooldown entre exécutions
- [ ] Tester les options de la commande
- [ ] Vérifier les logs dans CloudWatch
- [ ] Créer les tests unitaires et d'intégration
