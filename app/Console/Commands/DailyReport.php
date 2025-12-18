<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\ReportServiceInterface;
use App\Services\Notification\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Commande de génération et d'envoi du rapport quotidien.
 *
 * Cette commande génère un rapport quotidien des activités de trading,
 * calcule le P&L et l'envoie via Telegram.
 */
final class DailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:daily
        {--date= : Date for the report (YYYY-MM-DD, default: yesterday)}
        {--dry-run : Generate report without sending to Telegram}
        {--chat-id= : Override Telegram chat ID}
        {--v|verbose : Verbose output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and send daily trading report';

    public function __construct(
        private readonly ReportServiceInterface $reportService,
        private readonly TelegramService $telegramService,
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
        $dateOption = $this->option('date');
        $dryRun = $this->option('dry-run');
        $chatIdOption = $this->option('chat-id');

        // Déterminer la date du rapport
        $date = $dateOption ? Carbon::parse($dateOption) : Carbon::yesterday();

        // Log de début
        Log::info('Daily report generation started', [
            'date' => $date->format('Y-m-d'),
            'dry_run' => $dryRun,
            'chat_id_override' => $chatIdOption,
        ]);

        $this->info('📊 Trading Bot - Daily Report Generation');
        $this->line('');
        $this->info("📅 Report Date: {$date->format('d/m/Y')}");

        if ($dryRun) {
            $this->warn('⚠️  DRY RUN MODE - Report will not be sent to Telegram');
        }

        $this->line('');

        try {
            // 1. Générer le rapport
            $this->info('🔍 Generating report...');

            $report = $this->reportService->generateDailyReport($date);

            // 2. Afficher le résumé
            $this->line('');
            $this->displayReportSummary($report);

            // 3. Envoyer via Telegram (sauf si dry-run)
            if (! $dryRun) {
                $this->line('');
                $this->info('📬 Sending report to Telegram...');

                $sent = $this->telegramService->sendDailyReport($report);

                if ($sent) {
                    $this->info('✓ Report sent successfully');
                } else {
                    $this->warn('⚠️  Failed to send report to Telegram');
                }
            } else {
                $this->line('');
                $this->warn('⏭️  Skipping Telegram sending (dry-run mode)');
            }

            // 4. Archiver le rapport
            $this->line('');
            $this->info('💾 Archiving report...');

            $archived = $this->reportService->archiveReport($report);

            if ($archived) {
                $this->info('✓ Report archived successfully');
            } else {
                $this->warn('⚠️  Failed to archive report');
            }

            // 5. Afficher les détails des trades (mode verbose)
            if ($this->option('verbose') && ! empty($report->trades)) {
                $this->line('');
                $this->displayTradesDetails($report->trades);
            }

            // 6. Log de fin
            $duration = now()->diffInSeconds($startTime);
            Log::info('Daily report generation completed', [
                'date' => $date->format('Y-m-d'),
                'total_trades' => $report->totalTrades,
                'pnl' => $report->totalPnl,
                'sent' => ! $dryRun,
                'duration_seconds' => $duration,
            ]);

            $this->line('');
            $this->info("✓ Report generation completed in {$duration}s");

            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->line('');
            $this->error('❌ Report generation failed: '.$e->getMessage());

            Log::error('Daily report generation failed', [
                'date' => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->option('verbose')) {
                $this->line('');
                $this->error('Stack trace:');
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Affiche le résumé du rapport.
     */
    private function displayReportSummary($report): void
    {
        $this->info('📈 Report Summary:');
        $this->line('');

        // Trades
        $this->line("  <fg=cyan>Trades:</>");
        $this->line("    • Total: {$report->totalTrades}");
        $this->line("    • Buys: {$report->buyCount}");
        $this->line("    • Sells: {$report->sellCount}");

        // P&L
        $this->line('');
        $this->line("  <fg=cyan>Performance:</>");
        $pnlColor = $report->totalPnl >= 0 ? 'green' : 'red';
        $pnlSign = $report->totalPnl >= 0 ? '+' : '';
        $this->line("    • P&L: <fg={$pnlColor}>{$pnlSign}".number_format($report->totalPnl, 2).' USDT</> ('.
            $pnlSign.number_format($report->totalPnlPercent, 2).'%)');

        // Portfolio
        $this->line('');
        $this->line("  <fg=cyan>Portfolio:</>");
        $this->line('    • Total Value: '.number_format($report->totalBalanceUsdt, 2).' USDT');

        if (! empty($report->balances)) {
            $this->line('    • Balances:');
            foreach ($report->balances as $asset => $balance) {
                if ($balance > 0) {
                    $this->line("      - {$asset}: ".number_format($balance, 8));
                }
            }
        }
    }

    /**
     * Affiche les détails des trades (mode verbose).
     */
    private function displayTradesDetails(array $trades): void
    {
        $this->info('📋 Trades Details:');
        $this->line('');

        $headers = ['Time', 'Side', 'Symbol', 'Quantity', 'Price', 'Total', 'P&L'];
        $rows = [];

        foreach ($trades as $trade) {
            $sideColor = $trade->side->value === 'BUY' ? 'green' : 'red';
            $pnlDisplay = $trade->pnl !== null
                ? ($trade->pnl >= 0 ? '+' : '').number_format($trade->pnl, 2)
                : '-';

            $rows[] = [
                $trade->createdAt->format('H:i:s'),
                "<fg={$sideColor}>{$trade->side->value}</>",
                $trade->symbol,
                number_format($trade->quantity, 8),
                number_format($trade->price, 2),
                number_format($trade->quoteQuantity, 2),
                $pnlDisplay,
            ];
        }

        $this->table($headers, $rows);
    }
}
