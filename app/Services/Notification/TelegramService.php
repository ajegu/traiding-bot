<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\DTOs\DailyReportDTO;
use App\Enums\OrderSide;
use App\Models\Trade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'envoi de notifications via Telegram Bot API.
 */
final class TelegramService
{
    private const API_BASE_URL = 'https://api.telegram.org/bot';

    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 1000;

    public function __construct(
        private readonly string $botToken,
        private readonly string $chatId,
        private readonly bool $enabled = true,
    ) {}

    /**
     * Envoie un message simple.
     */
    public function sendMessage(string $text, ?string $parseMode = 'MarkdownV2'): bool
    {
        if (! $this->enabled) {
            Log::info('Telegram notifications disabled, skipping message');

            return false;
        }

        if (strlen($text) > 4096) {
            Log::warning('Telegram message too long, truncating', [
                'length' => strlen($text),
            ]);
            $text = substr($text, 0, 4093).'\.\.\.';
        }

        return $this->executeWithRetry(function () use ($text, $parseMode) {
            $response = Http::post($this->getApiUrl('sendMessage'), [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::error('Telegram API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException("Telegram API error: {$response->status()}");
            }

            Log::info('Telegram message sent', [
                'message_id' => $response->json('result.message_id'),
            ]);

            return true;
        });
    }

    /**
     * Envoie une notification de trade exécuté.
     */
    public function sendTradeNotification(Trade $trade): bool
    {
        $emoji = $trade->side === OrderSide::Buy ? '🟢' : '🔴';
        $sideLabel = $trade->side === OrderSide::Buy ? 'BUY' : 'SELL';

        $message = "{$emoji} *Trade Exécuté*\n\n";
        $message .= "*{$sideLabel}* ".number_format($trade->quantity, 8).' ';
        $message .= $this->escapeMarkdownV2(str_replace('USDT', '', $trade->symbol));
        $message .= ' @ '.number_format($trade->price, 2)." USDT\n";
        $message .= 'Total: '.number_format($trade->quoteQuantity, 2)." USDT\n\n";

        if ($trade->strategy !== null) {
            $message .= '📊 Stratégie: '.$this->escapeMarkdownV2($trade->strategy)."\n";
        }

        $message .= '⏰ '.$this->escapeMarkdownV2($trade->createdAt->format('d/m/Y H:i:s'));

        if ($trade->pnl !== null && $trade->side === OrderSide::Sell) {
            $pnlEmoji = $trade->pnl >= 0 ? '💰' : '⚠️';
            $pnlSign = $trade->pnl >= 0 ? '+' : '';
            $message .= "\n\n{$pnlEmoji} P&L: {$pnlSign}".number_format($trade->pnl, 2).' USDT';

            if ($trade->pnlPercent !== null) {
                $message .= ' \\('.$pnlSign.number_format($trade->pnlPercent, 2).'%\\)';
            }
        }

        return $this->sendMessage($message);
    }

    /**
     * Envoie une notification d'erreur.
     */
    public function sendErrorNotification(string $type, string $message, array $context = []): bool
    {
        $text = "🔴 *Erreur*\n\n";
        $text .= "*Type:* ".$this->escapeMarkdownV2($type)."\n";
        $text .= "*Message:* ".$this->escapeMarkdownV2($message)."\n";

        if (! empty($context)) {
            $text .= "\n*Contexte:*\n";
            foreach ($context as $key => $value) {
                $text .= "• ".$this->escapeMarkdownV2("{$key}: {$value}")."\n";
            }
        }

        $text .= "\n⏰ ".$this->escapeMarkdownV2(now()->format('d/m/Y H:i:s'));

        return $this->sendMessage($text);
    }

    /**
     * Envoie le rapport quotidien.
     */
    public function sendDailyReport(DailyReportDTO $report): bool
    {
        $message = "📊 *Rapport Trading \\- ".$this->escapeMarkdownV2($report->date->format('d/m/Y'))."*\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";

        // Section Trades
        $message .= "📈 *Trades du jour* \\(".$report->totalTrades."\\)\n\n";

        if ($report->totalTrades > 0) {
            foreach ($report->trades as $trade) {
                $emoji = $trade->side === OrderSide::Buy ? '🟢' : '🔴';
                $side = $trade->side === OrderSide::Buy ? 'BUY' : 'SELL';
                $symbol = str_replace('USDT', '', $trade->symbol);
                $time = $trade->createdAt->format('H:i');

                $message .= "{$emoji} {$side} ".number_format($trade->quantity, 8).' ';
                $message .= $this->escapeMarkdownV2($symbol);
                $message .= ' @ '.number_format($trade->price, 2)." USDT \\({$time}\\)\n";
            }
        } else {
            $message .= "Aucun trade exécuté aujourd'hui\\.\n";
        }

        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";

        // Section Performance
        $message .= "💰 *Performance*\n\n";

        $pnlEmoji = $report->totalPnl >= 0 ? '📈' : '📉';
        $pnlSign = $report->totalPnl >= 0 ? '+' : '';
        $message .= "• P&L : {$pnlEmoji} {$pnlSign}".number_format($report->totalPnl, 2).' USDT ';
        $message .= '\\('.$pnlSign.number_format($report->totalPnlPercent, 2).'%\\)'."\n";
        $message .= "• Trades : {$report->totalTrades} \\({$report->buyCount} achats, {$report->sellCount} ventes\\)\n";

        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";

        // Section Soldes
        $message .= "🏦 *Solde actuel*\n\n";

        foreach ($report->balances as $asset => $balance) {
            if ($balance > 0) {
                $message .= "• ".$this->escapeMarkdownV2($asset).' : '.number_format($balance, 8)."\n";
            }
        }

        $message .= "\n💎 *Total* : ".number_format($report->totalBalanceUsdt, 2)." USDT\n";

        $message .= "\n━━━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "_Généré automatiquement par Trading Bot_";

        return $this->sendMessage($message);
    }

    /**
     * Échappe les caractères spéciaux pour MarkdownV2.
     *
     * Caractères à échapper : _ * [ ] ( ) ~ ` > # + - = | { } . !
     */
    public function escapeMarkdownV2(string $text): string
    {
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\'.$char, $text);
        }

        return $text;
    }

    /**
     * Construit l'URL de l'API Telegram.
     */
    private function getApiUrl(string $method): string
    {
        return self::API_BASE_URL.$this->botToken.'/'.$method;
    }

    /**
     * Exécute une opération avec retry en cas d'erreur.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function executeWithRetry(callable $operation): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $operation();
            } catch (\RuntimeException $e) {
                $lastException = $e;

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * $attempt;
                    Log::warning('Telegram API error, retrying', [
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);

                    usleep($delay * 1000);
                } else {
                    Log::error('Telegram API error after all retries', [
                        'attempts' => self::MAX_RETRIES,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        throw $lastException;
    }
}
