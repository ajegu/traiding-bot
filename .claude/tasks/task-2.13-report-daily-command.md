# Task 2.13 - Commande report:daily

## Objectif

Créer la commande Artisan `report:daily` qui génère et envoie le rapport quotidien de trading via Telegram. Cette commande est déclenchée chaque jour à 08h00 UTC par EventBridge.

## Dépendances

- **Task 2.5** : Repositories (TradeRepository, ReportRepository)
- **Task 2.6** : BinanceService (soldes)
- **Task 2.10** : TelegramService
- **Task 2.11** : ReportService

## Fichiers à créer

```
app/
└── Console/
    └── Commands/
        └── ReportDaily.php
```

## Implémentation

### app/Console/Commands/ReportDaily.php

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\ReportRepositoryInterface;
use App\Contracts\TelegramServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\DTOs\DailyReportDTO;
use App\Exceptions\TelegramException;
use App\Services\Reporting\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReportDaily extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'report:daily
        {--date= : Specific date for the report (YYYY-MM-DD format, default: yesterday)}
        {--chat-id= : Override Telegram chat ID}
        {--dry-run : Generate report without sending to Telegram}
        {--save : Save report to DynamoDB even in dry-run mode}';

    /**
     * The console command description.
     */
    protected $description = 'Generate and send the daily trading report via Telegram';

    public function __construct(
        private readonly ReportService $reportService,
        private readonly TelegramServiceInterface $telegramService,
        private readonly ReportRepositoryInterface $reportRepository,
        private readonly TradeRepositoryInterface $tradeRepository,
        private readonly BinanceServiceInterface $binanceService,
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
            // Déterminer la date du rapport
            $date = $this->resolveDate();
            $dryRun = (bool) $this->option('dry-run');
            $save = (bool) $this->option('save');
            $chatId = $this->option('chat-id');

            Log::info('Daily report generation started', [
                'date' => $date->toDateString(),
                'dry_run' => $dryRun,
            ]);

            $this->info("Generating daily report for {$date->toDateString()}...");
            $this->newLine();

            // Vérifier si un rapport existe déjà pour cette date
            if ($this->reportAlreadyExists($date) && !$dryRun) {
                $this->warn("Report for {$date->toDateString()} already exists.");

                if (!$this->confirm('Do you want to regenerate and resend it?', false)) {
                    return Command::SUCCESS;
                }
            }

            // Générer le rapport
            $this->info('Fetching trades...');
            $report = $this->reportService->generateDailyReport($date);

            // Afficher le résumé
            $this->displayReportSummary($report);

            // Générer le message formaté
            $this->info('Formatting message...');
            $message = $this->reportService->formatReportForTelegram($report);

            if ($dryRun) {
                $this->newLine();
                $this->info('=== DRY RUN - Message Preview ===');
                $this->newLine();
                $this->line($this->stripMarkdown($message));
                $this->newLine();
                $this->info('=== End of Preview ===');

                if ($save) {
                    $this->saveReport($report, null);
                    $this->info('Report saved to DynamoDB.');
                }
            } else {
                // Envoyer via Telegram
                $this->info('Sending to Telegram...');
                $messageId = $this->sendTelegramReport($message, $chatId);

                // Sauvegarder le rapport
                $this->saveReport($report, $messageId);

                $this->newLine();
                $this->info("✓ Report sent successfully! (Message ID: {$messageId})");
            }

            $executionTime = round((microtime(true) - $startTime) * 1000);

            Log::info('Daily report generation completed', [
                'date' => $date->toDateString(),
                'trades_count' => $report->totalTrades,
                'pnl' => $report->totalPnl,
                'dry_run' => $dryRun,
                'execution_time_ms' => $executionTime,
            ]);

            $this->newLine();
            $this->info("Completed in {$executionTime}ms");

            return Command::SUCCESS;

        } catch (TelegramException $e) {
            return $this->handleTelegramError($e);

        } catch (Throwable $e) {
            return $this->handleUnexpectedError($e);
        }
    }

    /**
     * Resolve the report date from options.
     */
    private function resolveDate(): Carbon
    {
        $dateOption = $this->option('date');

        if ($dateOption !== null) {
            $date = Carbon::parse($dateOption);

            if ($date->isFuture()) {
                throw new \InvalidArgumentException('Report date cannot be in the future.');
            }

            return $date->startOfDay();
        }

        // Par défaut : hier
        return Carbon::yesterday()->startOfDay();
    }

    /**
     * Check if a report already exists for the given date.
     */
    private function reportAlreadyExists(Carbon $date): bool
    {
        $existingReport = $this->reportRepository->findByDate($date);

        return $existingReport !== null;
    }

    /**
     * Display report summary in console.
     */
    private function displayReportSummary(DailyReportDTO $report): void
    {
        $this->newLine();
        $this->info('=== Report Summary ===');
        $this->newLine();

        // Statistiques des trades
        $this->table(
            ['Metric', 'Value'],
            [
                ['Date', $report->date->format('Y-m-d')],
                ['Total Trades', $report->totalTrades],
                ['Buy Orders', $report->buyCount],
                ['Sell Orders', $report->sellCount],
            ]
        );

        // Performance
        $pnlColor = $report->totalPnl >= 0 ? 'green' : 'red';
        $pnlSign = $report->totalPnl >= 0 ? '+' : '';

        $this->newLine();
        $this->info('Performance:');
        $this->line("  P&L: <fg={$pnlColor}>{$pnlSign}" . number_format($report->totalPnl, 2) . " USDT ({$pnlSign}" . number_format($report->totalPnlPercent, 2) . "%)</>");

        // Soldes
        $this->newLine();
        $this->info('Balances:');

        $balanceRows = [];
        foreach ($report->balances as $balance) {
            if ($balance->total > 0) {
                $balanceRows[] = [
                    $balance->asset,
                    number_format($balance->available, 8),
                    number_format($balance->locked, 8),
                    number_format($balance->total, 8),
                ];
            }
        }

        if (!empty($balanceRows)) {
            $this->table(
                ['Asset', 'Available', 'Locked', 'Total'],
                $balanceRows
            );
        }

        $this->newLine();
        $this->line("  <fg=cyan>Total Portfolio Value: " . number_format($report->totalBalanceUsdt, 2) . " USDT</>");

        // Liste des trades
        if ($report->totalTrades > 0) {
            $this->newLine();
            $this->info('Trades:');

            $tradeRows = [];
            foreach ($report->trades as $trade) {
                $sideColor = $trade->side === \App\Enums\OrderSide::Buy ? 'green' : 'red';
                $tradeRows[] = [
                    $trade->executedAt->format('H:i'),
                    "<fg={$sideColor}>{$trade->side->value}</>",
                    $trade->symbol,
                    number_format($trade->quantity, 8),
                    number_format($trade->price, 2),
                    number_format($trade->quoteQuantity, 2) . ' USDT',
                ];
            }

            $this->table(
                ['Time', 'Side', 'Symbol', 'Qty', 'Price', 'Total'],
                $tradeRows
            );
        }
    }

    /**
     * Send report to Telegram.
     */
    private function sendTelegramReport(string $message, ?string $chatId): int
    {
        return $this->telegramService->sendMessage(
            message: $message,
            chatId: $chatId,
            parseMode: 'MarkdownV2',
        );
    }

    /**
     * Save report to DynamoDB.
     */
    private function saveReport(DailyReportDTO $report, ?int $telegramMessageId): void
    {
        $this->reportRepository->save(
            date: Carbon::instance($report->date),
            tradesCount: $report->totalTrades,
            buyCount: $report->buyCount,
            sellCount: $report->sellCount,
            pnlAbsolute: $report->totalPnl,
            pnlPercent: $report->totalPnlPercent,
            totalBalanceUsdt: $report->totalBalanceUsdt,
            balances: array_map(fn($b) => $b->toArray(), $report->balances),
            telegramMessageId: $telegramMessageId,
        );
    }

    /**
     * Strip markdown for console preview.
     */
    private function stripMarkdown(string $text): string
    {
        // Supprime les caractères d'échappement MarkdownV2
        $text = str_replace('\\', '', $text);

        // Remplace les marqueurs de formatage
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text);
        $text = preg_replace('/_([^_]+)_/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        return $text;
    }

    /**
     * Handle Telegram error.
     */
    private function handleTelegramError(TelegramException $e): int
    {
        $this->error("Telegram error: {$e->getMessage()}");

        Log::error('Daily report failed: Telegram error', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
        ]);

        // Sauvegarder le rapport avec statut d'échec si possible
        try {
            $date = $this->resolveDate();
            $report = $this->reportService->generateDailyReport($date);
            $this->saveReport($report, null);
            $this->warn('Report saved to DynamoDB without Telegram message ID.');
        } catch (Throwable) {
            // Ignore
        }

        report($e);

        return Command::FAILURE;
    }

    /**
     * Handle unexpected error.
     */
    private function handleUnexpectedError(Throwable $e): int
    {
        $this->error("Unexpected error: {$e->getMessage()}");

        Log::error('Daily report failed: unexpected error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        report($e);

        return Command::FAILURE;
    }
}
```

## Tests

### tests/Feature/Console/ReportDailyCommandTest.php

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\ReportRepositoryInterface;
use App\Contracts\TelegramServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\DTOs\BalanceDTO;
use App\DTOs\DailyReportDTO;
use App\DTOs\TradeResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Exceptions\TelegramException;
use App\Services\Reporting\ReportService;
use Carbon\Carbon;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ReportDailyCommandTest extends TestCase
{
    private MockInterface $reportService;
    private MockInterface $telegramService;
    private MockInterface $reportRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportService = Mockery::mock(ReportService::class);
        $this->telegramService = Mockery::mock(TelegramServiceInterface::class);
        $this->reportRepository = Mockery::mock(ReportRepositoryInterface::class);

        $this->app->instance(ReportService::class, $this->reportService);
        $this->app->instance(TelegramServiceInterface::class, $this->telegramService);
        $this->app->instance(ReportRepositoryInterface::class, $this->reportRepository);
    }

    public function test_generates_and_sends_daily_report(): void
    {
        // Arrange
        $report = $this->createMockReport();

        $this->reportRepository
            ->shouldReceive('findByDate')
            ->once()
            ->andReturn(null);

        $this->reportService
            ->shouldReceive('generateDailyReport')
            ->once()
            ->andReturn($report);

        $this->reportService
            ->shouldReceive('formatReportForTelegram')
            ->once()
            ->with($report)
            ->andReturn('Formatted message');

        $this->telegramService
            ->shouldReceive('sendMessage')
            ->once()
            ->with('Formatted message', null, 'MarkdownV2')
            ->andReturn(12345);

        $this->reportRepository
            ->shouldReceive('save')
            ->once();

        // Act & Assert
        $this->artisan('report:daily')
            ->assertSuccessful()
            ->expectsOutputToContain('Report sent successfully');
    }

    public function test_dry_run_does_not_send_telegram_message(): void
    {
        // Arrange
        $report = $this->createMockReport();

        $this->reportRepository
            ->shouldReceive('findByDate')
            ->never();

        $this->reportService
            ->shouldReceive('generateDailyReport')
            ->once()
            ->andReturn($report);

        $this->reportService
            ->shouldReceive('formatReportForTelegram')
            ->once()
            ->andReturn('Formatted message');

        $this->telegramService
            ->shouldNotReceive('sendMessage');

        $this->reportRepository
            ->shouldNotReceive('save');

        // Act & Assert
        $this->artisan('report:daily', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('DRY RUN');
    }

    public function test_dry_run_with_save_option_saves_to_dynamodb(): void
    {
        // Arrange
        $report = $this->createMockReport();

        $this->reportService
            ->shouldReceive('generateDailyReport')
            ->once()
            ->andReturn($report);

        $this->reportService
            ->shouldReceive('formatReportForTelegram')
            ->once()
            ->andReturn('Formatted message');

        $this->telegramService
            ->shouldNotReceive('sendMessage');

        $this->reportRepository
            ->shouldReceive('save')
            ->once()
            ->with(
                Mockery::type(Carbon::class),
                3,
                2,
                1,
                Mockery::type('float'),
                Mockery::type('float'),
                Mockery::type('float'),
                Mockery::type('array'),
                null
            );

        // Act & Assert
        $this->artisan('report:daily', ['--dry-run' => true, '--save' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Report saved to DynamoDB');
    }

    public function test_uses_specific_date_when_provided(): void
    {
        // Arrange
        $report = $this->createMockReport();
        $specificDate = Carbon::parse('2024-12-01');

        $this->reportRepository
            ->shouldReceive('findByDate')
            ->once()
            ->with(Mockery::on(fn($date) => $date->toDateString() === '2024-12-01'))
            ->andReturn(null);

        $this->reportService
            ->shouldReceive('generateDailyReport')
            ->once()
            ->with(Mockery::on(fn($date) => $date->toDateString() === '2024-12-01'))
            ->andReturn($report);

        $this->reportService
            ->shouldReceive('formatReportForTelegram')
            ->once()
            ->andReturn('Formatted message');

        $this->telegramService
            ->shouldReceive('sendMessage')
            ->once()
            ->andReturn(12345);

        $this->reportRepository
            ->shouldReceive('save')
            ->once();

        // Act & Assert
        $this->artisan('report:daily', ['--date' => '2024-12-01'])
            ->assertSuccessful();
    }

    public function test_rejects_future_date(): void
    {
        // Arrange
        $futureDate = Carbon::tomorrow()->addDay()->toDateString();

        // Act & Assert
        $this->artisan('report:daily', ['--date' => $futureDate])
            ->assertFailed();
    }

    public function test_uses_custom_chat_id_when_provided(): void
    {
        // Arrange
        $report = $this->createMockReport();
        $customChatId = '987654321';

        $this->reportRepository
            ->shouldReceive('findByDate')
            ->once()
            ->andReturn(null);

        $this->reportService
            ->shouldReceive('generateDailyReport')
            ->once()
            ->andReturn($report);

        $this->reportService
            ->shouldReceive('formatReportForTelegram')
            ->once()
            ->andReturn('Formatted message');

        $this->telegramService
            ->shouldReceive('sendMessage')
            ->once()
            ->with('Formatted message', $customChatId, 'MarkdownV2')
            ->andReturn(12345);

        $this->reportRepository
            ->shouldReceive('save')
            ->once();

        // Act & Assert
        $this->artisan('report:daily', ['--chat-id' => $customChatId])
            ->assertSuccessful();
    }

    public function test_handles_telegram_error_gracefully(): void
    {
        // Arrange
        $report = $this->createMockReport();

        $this->reportRepository
            ->shouldReceive('findByDate')
            ->once()
            ->andReturn(null);

        $this->reportService
            ->shouldReceive('generateDailyReport')
            ->twice() // Once for send, once for recovery save
            ->andReturn($report);

        $this->reportService
            ->shouldReceive('formatReportForTelegram')
            ->once()
            ->andReturn('Formatted message');

        $this->telegramService
            ->shouldReceive('sendMessage')
            ->once()
            ->andThrow(new TelegramException('Bot blocked by user', 403));

        $this->reportRepository
            ->shouldReceive('save')
            ->once()
            ->with(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                null // No message ID due to failure
            );

        // Act & Assert
        $this->artisan('report:daily')
            ->assertFailed()
            ->expectsOutputToContain('Telegram error');
    }

    public function test_report_with_no_trades(): void
    {
        // Arrange
        $report = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-05'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [
                new BalanceDTO('USDT', 1000.0, 0.0),
            ],
            totalBalanceUsdt: 1000.0,
            trades: [],
        );

        $this->reportRepository
            ->shouldReceive('findByDate')
            ->once()
            ->andReturn(null);

        $this->reportService
            ->shouldReceive('generateDailyReport')
            ->once()
            ->andReturn($report);

        $this->reportService
            ->shouldReceive('formatReportForTelegram')
            ->once()
            ->andReturn('No trades today');

        $this->telegramService
            ->shouldReceive('sendMessage')
            ->once()
            ->andReturn(12345);

        $this->reportRepository
            ->shouldReceive('save')
            ->once();

        // Act & Assert
        $this->artisan('report:daily')
            ->assertSuccessful()
            ->expectsOutputToContain('Total Trades')
            ->expectsOutputToContain('0');
    }

    /**
     * Create a mock report for testing.
     */
    private function createMockReport(): DailyReportDTO
    {
        return new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-05'),
            totalTrades: 3,
            buyCount: 2,
            sellCount: 1,
            totalPnl: 150.50,
            totalPnlPercent: 1.5,
            balances: [
                new BalanceDTO('BTC', 0.5, 0.0),
                new BalanceDTO('USDT', 10000.0, 500.0),
            ],
            totalBalanceUsdt: 31250.0,
            trades: [
                new TradeResultDTO(
                    orderId: '1',
                    symbol: 'BTCUSDT',
                    side: OrderSide::Buy,
                    status: OrderStatus::Filled,
                    quantity: 0.001,
                    price: 42500.0,
                    quoteQuantity: 42.5,
                    commission: 0.04,
                    commissionAsset: 'USDT',
                    executedAt: new DateTimeImmutable('2024-12-05 10:30:00'),
                ),
                new TradeResultDTO(
                    orderId: '2',
                    symbol: 'BTCUSDT',
                    side: OrderSide::Buy,
                    status: OrderStatus::Filled,
                    quantity: 0.001,
                    price: 42000.0,
                    quoteQuantity: 42.0,
                    commission: 0.04,
                    commissionAsset: 'USDT',
                    executedAt: new DateTimeImmutable('2024-12-05 14:00:00'),
                ),
                new TradeResultDTO(
                    orderId: '3',
                    symbol: 'BTCUSDT',
                    side: OrderSide::Sell,
                    status: OrderStatus::Filled,
                    quantity: 0.002,
                    price: 43000.0,
                    quoteQuantity: 86.0,
                    commission: 0.08,
                    commissionAsset: 'USDT',
                    executedAt: new DateTimeImmutable('2024-12-05 18:45:00'),
                ),
            ],
        );
    }
}
```

## Usage

```bash
# Rapport du jour précédent (défaut)
php artisan report:daily

# Rapport pour une date spécifique
php artisan report:daily --date=2024-12-05

# Mode dry-run (prévisualisation sans envoi)
php artisan report:daily --dry-run

# Dry-run avec sauvegarde en base
php artisan report:daily --dry-run --save

# Envoyer à un chat Telegram différent
php artisan report:daily --chat-id=123456789

# Combinaison d'options
php artisan report:daily --date=2024-12-01 --dry-run
```

## Configuration EventBridge

La commande est déclenchée par EventBridge chaque jour à 08h00 UTC :

```hcl
# terraform/modules/lambda/main.tf
resource "aws_cloudwatch_event_rule" "daily_report" {
  name                = "${var.name_prefix}-rule-daily-report"
  description         = "Send daily trading report at 08:00 UTC"
  schedule_expression = "cron(0 8 * * ? *)"
}

resource "aws_cloudwatch_event_target" "daily_report" {
  rule      = aws_cloudwatch_event_rule.daily_report.name
  target_id = "DailyReportGenerator"
  arn       = aws_lambda_function.artisan.arn
  input     = jsonencode({
    cli = "report:daily"
  })
}
```

## Exemple de Rapport Telegram

```
📊 Rapport Trading - 05/12/2024

━━━━━━━━━━━━━━━━━━━━

📈 Trades du jour (3)

🟢 BUY 0.001 BTC @ 42,500 USDT (10:30)
🟢 BUY 0.001 BTC @ 42,000 USDT (14:00)
🔴 SELL 0.002 BTC @ 43,000 USDT (18:45)

━━━━━━━━━━━━━━━━━━━━

💰 Performance

• P&L : +150.50 USDT (+1.5%)
• Trades : 3 (2 achats, 1 vente)
• Frais : -0.16 USDT

━━━━━━━━━━━━━━━━━━━━

🏦 Solde actuel

• BTC : 0.5 (~21,250 USDT)
• USDT : 10,000

💎 Total : ~31,250 USDT

━━━━━━━━━━━━━━━━━━━━

⚙️ Statut

• Bot : 🟢 Actif
• Stratégie : RSI

Généré automatiquement par Trading Bot
```

## Checklist

- [ ] Créer `app/Console/Commands/ReportDaily.php`
- [ ] Tester la commande avec `--dry-run`
- [ ] Vérifier le formatage MarkdownV2
- [ ] Tester l'envoi Telegram
- [ ] Vérifier la sauvegarde en DynamoDB
- [ ] Tester la gestion des erreurs
- [ ] Tester avec une date spécifique
- [ ] Vérifier les logs dans CloudWatch
- [ ] Créer les tests unitaires et d'intégration
- [ ] Configurer la règle EventBridge (08h00 UTC)
