<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\BotConfigRepositoryInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\TradingServiceInterface;
use App\Enums\Signal;
use App\Enums\Strategy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Commande d'exécution du bot de trading.
 *
 * Cette commande analyse le marché, calcule les indicateurs techniques
 * et exécute des trades selon la stratégie configurée.
 */
final class RunBot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:run
        {--dry-run : Execute without placing real orders}
        {--force : Run even if bot is disabled}
        {--symbol= : Trading pair symbol (default: from config)}
        {--strategy= : Trading strategy (rsi, ma, combined)}
        {--amount= : Trade amount in USDT}
        {--v|verbose : Verbose output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute the trading bot strategy';

    public function __construct(
        private readonly TradingServiceInterface $tradingService,
        private readonly BotConfigRepositoryInterface $botConfigRepository,
        private readonly NotificationServiceInterface $notificationService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = now();

        // Options
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $symbol = $this->option('symbol');
        $strategyOption = $this->option('strategy');
        $amount = $this->option('amount');

        // Log de début
        Log::info('Bot execution started', [
            'dry_run' => $dryRun,
            'force' => $force,
            'symbol' => $symbol,
            'strategy' => $strategyOption,
            'amount' => $amount,
        ]);

        $this->info('🤖 Trading Bot - Starting execution...');

        try {
            // 1. Vérifier si le bot est activé (sauf si --force)
            if (! $force) {
                $config = $this->botConfigRepository->get();

                if (! $config->enabled) {
                    $this->warn('⚠️  Bot is disabled. Use --force to run anyway.');
                    Log::info('Bot execution skipped: bot is disabled');

                    return self::SUCCESS;
                }

                $this->info('✓ Bot is enabled');
            } else {
                $this->warn('⚡ Running in forced mode (bot status ignored)');
            }

            // 2. Déterminer les paramètres d'exécution
            $config = $this->botConfigRepository->get();

            $symbol = $symbol ?? $config->symbol ?? config('bot.trading.symbol');
            $strategy = $strategyOption ? Strategy::from($strategyOption) : ($config->strategy ?? Strategy::from(config('bot.trading.strategy')));
            $amount = $amount ? (float) $amount : ($config->amount ?? config('bot.trading.amount'));

            $this->line('');
            $this->info("📊 Trading Parameters:");
            $this->line("  • Symbol: {$symbol}");
            $this->line("  • Strategy: {$strategy->value}");
            $this->line("  • Amount: {$amount} USDT");

            if ($dryRun) {
                $this->warn('  • Mode: DRY RUN (no real orders)');
            }

            $this->line('');

            // 3. Exécuter la stratégie
            $this->info('🔍 Analyzing market...');

            $result = $this->tradingService->executeStrategy(
                symbol: $symbol,
                strategy: $strategy,
                amount: $amount,
                dryRun: $dryRun
            );

            // 4. Afficher les résultats
            $this->line('');
            $this->displayResult($result->signal, $result->reason);

            // 5. Afficher les indicateurs
            if ($result->indicators !== null) {
                $this->line('');
                $this->info('📈 Technical Indicators:');

                if ($result->indicators->rsi !== null) {
                    $rsiColor = $result->indicators->rsi < 30 ? 'green' : ($result->indicators->rsi > 70 ? 'red' : 'yellow');
                    $this->line("  • RSI: <fg={$rsiColor}>{$result->indicators->rsi}</>");
                }

                if ($result->indicators->ma50 !== null) {
                    $this->line("  • MA50: {$result->indicators->ma50}");
                }

                if ($result->indicators->ma200 !== null) {
                    $this->line("  • MA200: {$result->indicators->ma200}");
                }

                if ($result->indicators->currentPrice !== null) {
                    $this->line("  • Current Price: {$result->indicators->currentPrice} USDT");
                }

                if ($result->indicators->goldenCross) {
                    $this->line('  • <fg=green>Golden Cross detected!</>');
                }

                if ($result->indicators->deathCross) {
                    $this->line('  • <fg=red>Death Cross detected!</>');
                }
            }

            // 6. Afficher le trade si exécuté
            if ($result->trade !== null && ! $dryRun) {
                $this->line('');
                $this->info('✅ Trade Executed:');
                $this->line("  • Side: {$result->trade->side->value}");
                $this->line("  • Quantity: {$result->trade->quantity}");
                $this->line("  • Price: {$result->trade->price} USDT");
                $this->line("  • Total: {$result->trade->quoteQuantity} USDT");
                $this->line("  • Order ID: {$result->trade->orderId}");

                // Notification
                if ($result->trade !== null) {
                    $this->line('');
                    $this->info('📬 Sending notifications...');
                }
            }

            // 7. Log de fin
            $duration = now()->diffInSeconds($startTime);
            Log::info('Bot execution completed', [
                'signal' => $result->signal->value,
                'trade_executed' => $result->trade !== null,
                'duration_seconds' => $duration,
            ]);

            $this->line('');
            $this->info("✓ Execution completed in {$duration}s");

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->line('');
            $this->error('❌ Bot execution failed: '.$e->getMessage());

            Log::error('Bot execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Notification d'erreur critique
            try {
                $this->notificationService->notifyCriticalError($e);
            } catch (Throwable $notificationError) {
                Log::error('Failed to send error notification', [
                    'error' => $notificationError->getMessage(),
                ]);
            }

            if ($this->option('verbose')) {
                $this->line('');
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Affiche le résultat de l'analyse.
     */
    private function displayResult(Signal $signal, ?string $reason): void
    {
        match ($signal) {
            Signal::Buy => $this->line('<fg=green>🟢 Signal: BUY</>'),
            Signal::Sell => $this->line('<fg=red>🔴 Signal: SELL</>'),
            Signal::Hold => $this->line('<fg=yellow>⚪ Signal: HOLD</>'),
        };

        if ($reason !== null) {
            $this->line("  Reason: {$reason}");
        }
    }
}
