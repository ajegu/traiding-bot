<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Contracts\NotificationServiceInterface;
use App\DTOs\DailyReportDTO;
use App\Models\Trade;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service orchestrateur de notifications.
 *
 * Coordonne l'envoi de notifications via plusieurs canaux (SNS, Telegram).
 */
final class NotificationService implements NotificationServiceInterface
{
    public function __construct(
        private readonly SnsNotificationService $snsService,
        private readonly TelegramService $telegramService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function notifyTradeExecuted(Trade $trade): void
    {
        Log::info('Sending trade notification', [
            'trade_id' => $trade->id,
            'symbol' => $trade->symbol,
            'side' => $trade->side->value,
        ]);

        // Envoi SNS (pour intégrations tierces, email, SQS)
        try {
            $this->snsService->publishTradeAlert($trade);
        } catch (Throwable $e) {
            Log::error('Failed to send SNS trade notification', [
                'trade_id' => $trade->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Envoi Telegram (pour notification utilisateur en temps réel)
        try {
            $this->telegramService->sendTradeNotification($trade);
        } catch (Throwable $e) {
            Log::error('Failed to send Telegram trade notification', [
                'trade_id' => $trade->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function notifyError(string $type, string $message, array $context = []): void
    {
        Log::info('Sending error notification', [
            'type' => $type,
            'message' => $message,
        ]);

        // Envoi SNS
        try {
            $this->snsService->publishErrorAlert($type, $message, $context);
        } catch (Throwable $e) {
            Log::error('Failed to send SNS error notification', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }

        // Envoi Telegram
        try {
            $this->telegramService->sendErrorNotification($type, $message, $context);
        } catch (Throwable $e) {
            Log::error('Failed to send Telegram error notification', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function notifyCriticalError(Throwable $exception): void
    {
        Log::critical('Sending critical error notification', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
        ]);

        // Envoi SNS (priorité haute)
        try {
            $this->snsService->publishCriticalError($exception);
        } catch (Throwable $e) {
            Log::error('Failed to send SNS critical error notification', [
                'original_exception' => get_class($exception),
                'error' => $e->getMessage(),
            ]);
        }

        // Envoi Telegram
        try {
            $errorMessage = sprintf(
                "🚨 *Erreur Critique*\n\n*Type:* %s\n*Message:* %s\n\n⏰ %s",
                $this->telegramService->escapeMarkdownV2(get_class($exception)),
                $this->telegramService->escapeMarkdownV2($exception->getMessage()),
                $this->telegramService->escapeMarkdownV2(now()->format('d/m/Y H:i:s'))
            );

            $this->telegramService->sendMessage($errorMessage);
        } catch (Throwable $e) {
            Log::error('Failed to send Telegram critical error notification', [
                'original_exception' => get_class($exception),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function notifyLowBalance(string $asset, float $balance, float $threshold): void
    {
        Log::info('Sending low balance notification', [
            'asset' => $asset,
            'balance' => $balance,
            'threshold' => $threshold,
        ]);

        // Envoi SNS
        try {
            $this->snsService->publishLowBalanceAlert($asset, $balance, $threshold);
        } catch (Throwable $e) {
            Log::error('Failed to send SNS low balance notification', [
                'asset' => $asset,
                'error' => $e->getMessage(),
            ]);
        }

        // Envoi Telegram
        try {
            $percentage = ($balance / $threshold) * 100;
            $message = sprintf(
                "⚠️ *Alerte Solde Bas*\n\n".
                "Votre solde %s est passé sous le seuil\\.\n\n".
                "💰 Solde actuel: %s %s\n".
                "📉 Seuil configuré: %s %s\n".
                "📊 Utilisation: %s%%\n\n".
                "⏰ %s",
                $this->telegramService->escapeMarkdownV2($asset),
                number_format($balance, 8),
                $this->telegramService->escapeMarkdownV2($asset),
                number_format($threshold, 2),
                $this->telegramService->escapeMarkdownV2($asset),
                number_format($percentage, 2),
                $this->telegramService->escapeMarkdownV2(now()->format('d/m/Y H:i:s'))
            );

            $this->telegramService->sendMessage($message);
        } catch (Throwable $e) {
            Log::error('Failed to send Telegram low balance notification', [
                'asset' => $asset,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function notifyPriceAlert(string $symbol, float $price, string $condition): void
    {
        Log::info('Sending price alert notification', [
            'symbol' => $symbol,
            'price' => $price,
            'condition' => $condition,
        ]);

        // Envoi SNS
        try {
            $this->snsService->publishPriceAlert($symbol, $price, $condition);
        } catch (Throwable $e) {
            Log::error('Failed to send SNS price alert notification', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
        }

        // Envoi Telegram
        try {
            $message = sprintf(
                "🔔 *Alerte de Prix*\n\n".
                "*Symbole:* %s\n".
                "*Prix actuel:* %s USDT\n".
                "*Condition:* %s\n\n".
                "⏰ %s",
                $this->telegramService->escapeMarkdownV2($symbol),
                number_format($price, 2),
                $this->telegramService->escapeMarkdownV2($condition),
                $this->telegramService->escapeMarkdownV2(now()->format('d/m/Y H:i:s'))
            );

            $this->telegramService->sendMessage($message);
        } catch (Throwable $e) {
            Log::error('Failed to send Telegram price alert notification', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function sendDailyReport(DailyReportDTO $report): void
    {
        Log::info('Sending daily report', [
            'date' => $report->date->format('Y-m-d'),
            'total_trades' => $report->totalTrades,
            'total_pnl' => $report->totalPnl,
        ]);

        // Le rapport quotidien est principalement envoyé via Telegram
        // SNS peut être utilisé pour archivage ou intégrations tierces
        try {
            $this->telegramService->sendDailyReport($report);
        } catch (Throwable $e) {
            Log::error('Failed to send Telegram daily report', [
                'date' => $report->date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            // Fallback: essayer de notifier l'échec via SNS
            try {
                $this->snsService->publishErrorAlert(
                    'DAILY_REPORT_FAILED',
                    'Failed to send daily report via Telegram',
                    [
                        'date' => $report->date->format('Y-m-d'),
                        'error' => $e->getMessage(),
                    ]
                );
            } catch (Throwable $snsException) {
                Log::error('Failed to send report failure notification', [
                    'error' => $snsException->getMessage(),
                ]);
            }
        }
    }
}
