# Task 2.10 - TelegramService (formatage MarkdownV2, sendMessage)

## Objectif

Créer le service d'envoi de messages Telegram pour le reporting quotidien et les alertes importantes.

## Fichiers à créer

| Fichier | Description |
|---------|-------------|
| `app/Contracts/TelegramServiceInterface.php` | Interface du service |
| `app/Services/Notification/TelegramService.php` | Service Telegram |
| `app/Services/Notification/TelegramFormatter.php` | Formatage MarkdownV2 |

## API Telegram

### Endpoint

```
https://api.telegram.org/bot<TOKEN>/sendMessage
```

### Paramètres

| Paramètre | Type | Requis | Description |
|-----------|------|--------|-------------|
| chat_id | int/string | Oui | ID du chat destinataire |
| text | string | Oui | Contenu du message (max 4096 chars) |
| parse_mode | string | Non | Format : MarkdownV2, HTML |
| disable_notification | bool | Non | Envoi silencieux |
| disable_web_page_preview | bool | Non | Désactiver les previews |

## Implémentation

### 1. Interface TelegramServiceInterface

**Créer** : `app/Contracts/TelegramServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\DailyReportDTO;
use App\DTOs\TradeResultDTO;

interface TelegramServiceInterface
{
    /**
     * Envoie un message texte simple.
     */
    public function sendMessage(string $text, ?string $chatId = null): ?int;

    /**
     * Envoie un message formaté en MarkdownV2.
     */
    public function sendMarkdownMessage(string $text, ?string $chatId = null): ?int;

    /**
     * Envoie une notification de trade.
     */
    public function sendTradeNotification(TradeResultDTO $trade): ?int;

    /**
     * Envoie le rapport quotidien.
     */
    public function sendDailyReport(DailyReportDTO $report): ?int;

    /**
     * Envoie une alerte d'erreur.
     */
    public function sendErrorAlert(string $type, string $message): ?int;

    /**
     * Vérifie si Telegram est activé et configuré.
     */
    public function isEnabled(): bool;
}
```

### 2. TelegramFormatter

**Créer** : `app/Services/Notification/TelegramFormatter.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\DTOs\BalanceDTO;
use App\DTOs\DailyReportDTO;
use App\DTOs\TradeResultDTO;

final class TelegramFormatter
{
    /**
     * Caractères spéciaux à échapper en MarkdownV2.
     */
    private const SPECIAL_CHARS = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];

    /**
     * Échappe les caractères spéciaux pour MarkdownV2.
     */
    public function escape(string $text): string
    {
        foreach (self::SPECIAL_CHARS as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }

        return $text;
    }

    /**
     * Formate un nombre avec séparateurs.
     */
    public function formatNumber(float $value, int $decimals = 2): string
    {
        return $this->escape(number_format($value, $decimals, '.', ','));
    }

    /**
     * Formate une notification de trade.
     */
    public function formatTradeNotification(TradeResultDTO $trade): string
    {
        $emoji = $trade->side->emoji();
        $sideLabel = $trade->side->label();
        $symbol = $this->escape($trade->symbol);
        $quantity = $this->formatNumber($trade->quantity, 6);
        $price = $this->formatNumber($trade->price, 2);
        $total = $this->formatNumber($trade->quoteQuantity, 2);
        $time = $trade->executedAt->format('H:i:s');

        return <<<MARKDOWN
{$emoji} *Trade Exécuté*

*{$sideLabel}* {$quantity} @ {$price} USDT
Total: {$total} USDT
Symbole: {$symbol}

⏰ {$this->escape($time)} UTC
MARKDOWN;
    }

    /**
     * Formate le rapport quotidien.
     */
    public function formatDailyReport(DailyReportDTO $report): string
    {
        $date = $this->escape($report->date->format('d/m/Y'));
        $tradesSection = $this->formatTradesSection($report);
        $performanceSection = $this->formatPerformanceSection($report);
        $balanceSection = $this->formatBalanceSection($report);
        $statusSection = $this->formatStatusSection();

        return <<<MARKDOWN
📊 *Rapport Trading \\- {$date}*

━━━━━━━━━━━━━━━━━━━━

{$tradesSection}

━━━━━━━━━━━━━━━━━━━━

{$performanceSection}

━━━━━━━━━━━━━━━━━━━━

{$balanceSection}

━━━━━━━━━━━━━━━━━━━━

{$statusSection}

_Généré automatiquement par Trading Bot_
MARKDOWN;
    }

    /**
     * Formate une alerte d'erreur.
     */
    public function formatErrorAlert(string $type, string $message): string
    {
        $escapedType = $this->escape($type);
        $escapedMessage = $this->escape($message);
        $time = $this->escape(now()->format('d/m/Y H:i:s'));

        return <<<MARKDOWN
🔴 *Erreur Critique*

*Type:* {$escapedType}
*Message:* {$escapedMessage}

⚠️ Action requise

⏰ {$time} UTC
MARKDOWN;
    }

    /**
     * Formate la section des trades.
     */
    private function formatTradesSection(DailyReportDTO $report): string
    {
        $count = $report->stats->totalTrades;

        if ($count === 0) {
            return "📈 *Trades du jour*\n\nAucun trade exécuté aujourd'hui\\.";
        }

        $lines = ["📈 *Trades du jour* \\({$count}\\)\n"];

        foreach (array_slice($report->trades, 0, 10) as $trade) {
            $emoji = $trade->side->emoji();
            $quantity = $this->formatNumber($trade->quantity, 6);
            $price = $this->formatNumber($trade->price, 2);
            $time = $this->escape($trade->executedAt->format('H:i'));

            $lines[] = "{$emoji} {$trade->side->value} {$quantity} @ {$price} USDT \\({$time}\\)";
        }

        if ($count > 10) {
            $remaining = $count - 10;
            $lines[] = "_\\.\\.\\. et {$remaining} autre\\(s\\)_";
        }

        return implode("\n", $lines);
    }

    /**
     * Formate la section performance.
     */
    private function formatPerformanceSection(DailyReportDTO $report): string
    {
        $stats = $report->stats;
        $pnlEmoji = $stats->totalPnl >= 0 ? '📈' : '📉';
        $pnlSign = $stats->totalPnl >= 0 ? '\\+' : '';
        $pnl = $this->formatNumber($stats->totalPnl, 2);
        $pnlPercent = $this->formatNumber($stats->totalPnlPercent, 2);
        $winning = $stats->winningTrades;
        $losing = $stats->losingTrades;
        $fees = $this->formatNumber($stats->totalFees, 4);

        return <<<MARKDOWN
💰 *Performance*

• P&L : {$pnlSign}{$pnl} USDT \\({$pnlSign}{$pnlPercent}%\\)
• Trades : {$stats->totalTrades} \\({$winning} gagnants, {$losing} perdants\\)
• Frais : \\-{$fees} USDT
MARKDOWN;
    }

    /**
     * Formate la section soldes.
     */
    private function formatBalanceSection(DailyReportDTO $report): string
    {
        $lines = ["🏦 *Solde actuel*\n"];

        foreach ($report->balances as $balance) {
            /** @var BalanceDTO $balance */
            if ($balance->total() > 0) {
                $total = $this->formatNumber($balance->total(), 8);
                $lines[] = "• {$this->escape($balance->asset)} : {$total}";
            }
        }

        $totalUsdt = $this->formatNumber($report->totalBalanceUsdt, 2);
        $lines[] = "\n💎 *Total* : ~{$totalUsdt} USDT";

        // Variation si disponible
        $changePercent = $report->dailyChangePercent();
        if ($changePercent !== null) {
            $sign = $changePercent >= 0 ? '\\+' : '';
            $change = $this->formatNumber($changePercent, 2);
            $changeEmoji = $changePercent >= 0 ? '📈' : '📉';
            $lines[] = "{$changeEmoji} Variation : {$sign}{$change}%";
        }

        return implode("\n", $lines);
    }

    /**
     * Formate la section statut.
     */
    private function formatStatusSection(): string
    {
        $botEnabled = config('bot.enabled', false);
        $botStatus = $botEnabled ? '🟢 Actif' : '🔴 Inactif';
        $strategy = $this->escape(config('bot.strategy.active', 'rsi'));

        return <<<MARKDOWN
⚙️ *Statut*

• Bot : {$botStatus}
• Stratégie : {$strategy}
MARKDOWN;
    }
}
```

### 3. TelegramService

**Créer** : `app/Services/Notification/TelegramService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Contracts\TelegramServiceInterface;
use App\DTOs\DailyReportDTO;
use App\DTOs\TradeResultDTO;
use App\Exceptions\NotificationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramService implements TelegramServiceInterface
{
    private const MAX_MESSAGE_LENGTH = 4096;
    private const API_TIMEOUT = 30;
    private const MAX_RETRIES = 3;

    private readonly string $botToken;
    private readonly string $chatId;
    private readonly string $apiUrl;

    public function __construct(
        private readonly TelegramFormatter $formatter,
    ) {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->chatId = config('services.telegram.chat_id', '');
        $this->apiUrl = config('services.telegram.api_url', 'https://api.telegram.org/bot');
    }

    public function sendMessage(string $text, ?string $chatId = null): ?int
    {
        return $this->send($text, $chatId, null);
    }

    public function sendMarkdownMessage(string $text, ?string $chatId = null): ?int
    {
        return $this->send($text, $chatId, 'MarkdownV2');
    }

    public function sendTradeNotification(TradeResultDTO $trade): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $message = $this->formatter->formatTradeNotification($trade);

        return $this->sendMarkdownMessage($message);
    }

    public function sendDailyReport(DailyReportDTO $report): ?int
    {
        if (!$this->isEnabled()) {
            Log::info('Telegram disabled, skipping daily report');
            return null;
        }

        $message = $this->formatter->formatDailyReport($report);

        return $this->sendMarkdownMessage($message);
    }

    public function sendErrorAlert(string $type, string $message): ?int
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $formattedMessage = $this->formatter->formatErrorAlert($type, $message);

        return $this->sendMarkdownMessage($formattedMessage);
    }

    public function isEnabled(): bool
    {
        return config('services.telegram.enabled', false)
            && !empty($this->botToken)
            && !empty($this->chatId);
    }

    /**
     * Envoie un message via l'API Telegram.
     */
    private function send(string $text, ?string $chatId, ?string $parseMode): ?int
    {
        if (!$this->isEnabled()) {
            Log::debug('Telegram not enabled, skipping message');
            return null;
        }

        $chatId ??= $this->chatId;

        // Tronquer si nécessaire
        if (mb_strlen($text) > self::MAX_MESSAGE_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_MESSAGE_LENGTH - 3) . '...';
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_web_page_preview' => true,
        ];

        if ($parseMode !== null) {
            $params['parse_mode'] = $parseMode;
        }

        return $this->executeWithRetry($params);
    }

    /**
     * Exécute la requête avec retry.
     */
    private function executeWithRetry(array $params, int $attempt = 1): ?int
    {
        try {
            $response = Http::timeout(self::API_TIMEOUT)
                ->post("{$this->apiUrl}{$this->botToken}/sendMessage", $params);

            $data = $response->json();

            if (!$response->successful() || !($data['ok'] ?? false)) {
                $errorCode = $data['error_code'] ?? $response->status();
                $description = $data['description'] ?? 'Unknown error';

                // Rate limit - attendre et retry
                if ($errorCode === 429 && $attempt < self::MAX_RETRIES) {
                    $retryAfter = $data['parameters']['retry_after'] ?? 5;
                    Log::warning('Telegram rate limited, retrying', [
                        'retry_after' => $retryAfter,
                        'attempt' => $attempt,
                    ]);
                    sleep($retryAfter);
                    return $this->executeWithRetry($params, $attempt + 1);
                }

                Log::error('Telegram API error', [
                    'error_code' => $errorCode,
                    'description' => $description,
                ]);

                throw new NotificationException(
                    message: "Telegram API error: {$description}",
                    channel: 'telegram',
                    context: ['error_code' => $errorCode],
                );
            }

            $messageId = $data['result']['message_id'] ?? null;

            Log::info('Telegram message sent', [
                'message_id' => $messageId,
                'chat_id' => $params['chat_id'],
            ]);

            return $messageId;

        } catch (NotificationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($attempt < self::MAX_RETRIES) {
                Log::warning('Telegram request failed, retrying', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                ]);
                sleep(1);
                return $this->executeWithRetry($params, $attempt + 1);
            }

            Log::error('Telegram request failed after retries', [
                'error' => $e->getMessage(),
            ]);

            throw new NotificationException(
                message: "Telegram request failed: {$e->getMessage()}",
                channel: 'telegram',
                previous: $e,
            );
        }
    }
}
```

### 4. Enregistrement dans le Service Provider

**Modifier** : `app/Providers/AppServiceProvider.php`

```php
// Ajouter dans les bindings
public array $bindings = [
    // ... autres bindings
    TelegramServiceInterface::class => TelegramService::class,
];

// Ajouter dans register()
public function register(): void
{
    // ... code existant

    $this->app->singleton(TelegramFormatter::class);
}
```

## Tests

**Créer** : `tests/Unit/Services/Notification/TelegramFormatterTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notification;

use App\DTOs\BalanceDTO;
use App\DTOs\DailyReportDTO;
use App\DTOs\TradeResultDTO;
use App\DTOs\TradeStatsDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Services\Notification\TelegramFormatter;
use DateTimeImmutable;
use Tests\TestCase;

final class TelegramFormatterTest extends TestCase
{
    private TelegramFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new TelegramFormatter();
    }

    public function test_escape_special_characters(): void
    {
        $text = 'Price: 42,500.00 (high) [test]';

        $escaped = $this->formatter->escape($text);

        $this->assertStringContainsString('\\(', $escaped);
        $this->assertStringContainsString('\\)', $escaped);
        $this->assertStringContainsString('\\[', $escaped);
        $this->assertStringContainsString('\\]', $escaped);
        $this->assertStringContainsString('\\.', $escaped);
    }

    public function test_format_number(): void
    {
        $formatted = $this->formatter->formatNumber(42500.123456, 2);

        $this->assertEquals('42,500\\.12', $formatted);
    }

    public function test_format_trade_notification(): void
    {
        $trade = new TradeResultDTO(
            orderId: '12345',
            clientOrderId: 'test',
            symbol: 'BTCUSDT',
            side: OrderSide::Buy,
            type: OrderType::Market,
            status: OrderStatus::Filled,
            quantity: 0.001,
            price: 42500.0,
            quoteQuantity: 42.50,
            commission: 0.04,
            commissionAsset: 'USDT',
            executedAt: new DateTimeImmutable(),
        );

        $message = $this->formatter->formatTradeNotification($trade);

        $this->assertStringContainsString('Trade Exécuté', $message);
        $this->assertStringContainsString('🟢', $message);
        $this->assertStringContainsString('BTCUSDT', $message);
    }

    public function test_format_daily_report(): void
    {
        $report = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: new TradeStatsDTO(
                totalTrades: 5,
                buyCount: 3,
                sellCount: 2,
                winningTrades: 3,
                losingTrades: 1,
                winRate: 75.0,
                totalPnl: 150.0,
                totalPnlPercent: 1.5,
                averagePnl: 37.5,
                bestTrade: 80.0,
                worstTrade: -20.0,
                totalVolume: 500.0,
                totalFees: 0.5,
            ),
            trades: [],
            balances: [new BalanceDTO('USDT', 10000.0, 0.0)],
            totalBalanceUsdt: 10000.0,
        );

        $message = $this->formatter->formatDailyReport($report);

        $this->assertStringContainsString('Rapport Trading', $message);
        $this->assertStringContainsString('06/12/2024', $message);
        $this->assertStringContainsString('Performance', $message);
        $this->assertStringContainsString('150', $message);
    }
}
```

**Créer** : `tests/Unit/Services/Notification/TelegramServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Notification;

use App\Services\Notification\TelegramFormatter;
use App\Services\Notification\TelegramService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class TelegramServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => 'test-token',
            'services.telegram.chat_id' => '123456789',
        ]);
    }

    public function test_send_message_success(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 999],
            ]),
        ]);

        $service = new TelegramService(new TelegramFormatter());
        $messageId = $service->sendMessage('Test message');

        $this->assertEquals(999, $messageId);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && $request['text'] === 'Test message';
        });
    }

    public function test_is_enabled_returns_false_when_not_configured(): void
    {
        config(['services.telegram.enabled' => false]);

        $service = new TelegramService(new TelegramFormatter());

        $this->assertFalse($service->isEnabled());
    }

    public function test_send_returns_null_when_disabled(): void
    {
        config(['services.telegram.enabled' => false]);

        $service = new TelegramService(new TelegramFormatter());
        $result = $service->sendMessage('Test');

        $this->assertNull($result);
    }
}
```

## Dépendances

- **Prérequis** : Tâches 2.4 (DTOs)
- **Infrastructure** : Token Telegram configuré (SSM ou .env)
- **Utilisé par** : Tâches 2.11 (ReportService), 2.13 (Commande report:daily)

## Checklist

- [ ] Créer `app/Contracts/TelegramServiceInterface.php`
- [ ] Créer `app/Services/Notification/TelegramFormatter.php`
- [ ] Créer `app/Services/Notification/TelegramService.php`
- [ ] Enregistrer dans `AppServiceProvider`
- [ ] Créer les tests unitaires
- [ ] Tester avec un vrai bot Telegram
- [ ] Vérifier le formatage MarkdownV2
- [ ] Vérifier avec `vendor/bin/pint`
