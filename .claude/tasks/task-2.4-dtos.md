# Task 2.4 - DTOs (TradeResult, TradeStats, DailyReport, Indicators)

## Objectif

Créer les Data Transfer Objects (DTOs) immutables pour transporter les données entre les couches de l'application.

## Fichiers à créer

| Fichier | Description |
|---------|-------------|
| `app/DTOs/TradeResultDTO.php` | Résultat d'un trade exécuté |
| `app/DTOs/TradeStatsDTO.php` | Statistiques de trading |
| `app/DTOs/DailyReportDTO.php` | Rapport quotidien |
| `app/DTOs/BalanceDTO.php` | Solde d'un actif |
| `app/DTOs/KlineDTO.php` | Données d'un chandelier |
| `app/DTOs/IndicatorsDTO.php` | Valeurs des indicateurs techniques |
| `app/DTOs/TradingResultDTO.php` | Résultat de l'analyse de stratégie |

## Principes

- Classes `final readonly`
- Constructeur avec `public` properties
- Méthodes `fromArray()` et `toArray()` pour la sérialisation
- Typage strict PHP 8.4
- Pas de logique métier (pure data)

## Implémentation

### 1. TradeResultDTO

**Créer** : `app/DTOs/TradeResultDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use DateTimeImmutable;

final readonly class TradeResultDTO
{
    public function __construct(
        public string $orderId,
        public string $clientOrderId,
        public string $symbol,
        public OrderSide $side,
        public OrderType $type,
        public OrderStatus $status,
        public float $quantity,
        public float $price,
        public float $quoteQuantity,
        public float $commission,
        public string $commissionAsset,
        public DateTimeImmutable $executedAt,
    ) {}

    /**
     * Crée une instance depuis une réponse Binance.
     */
    public static function fromBinanceResponse(array $response): self
    {
        $fills = $response['fills'] ?? [];
        $commission = 0.0;
        $commissionAsset = 'USDT';

        foreach ($fills as $fill) {
            $commission += (float) ($fill['commission'] ?? 0);
            $commissionAsset = $fill['commissionAsset'] ?? 'USDT';
        }

        return new self(
            orderId: (string) $response['orderId'],
            clientOrderId: $response['clientOrderId'] ?? '',
            symbol: $response['symbol'],
            side: OrderSide::from($response['side']),
            type: OrderType::from($response['type']),
            status: OrderStatus::from($response['status']),
            quantity: (float) $response['executedQty'],
            price: self::calculateAveragePrice($response),
            quoteQuantity: (float) $response['cummulativeQuoteQty'],
            commission: $commission,
            commissionAsset: $commissionAsset,
            executedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Calcule le prix moyen d'exécution.
     */
    private static function calculateAveragePrice(array $response): float
    {
        $fills = $response['fills'] ?? [];

        if (empty($fills)) {
            return (float) ($response['price'] ?? 0);
        }

        $totalQty = 0.0;
        $totalValue = 0.0;

        foreach ($fills as $fill) {
            $qty = (float) $fill['qty'];
            $price = (float) $fill['price'];
            $totalQty += $qty;
            $totalValue += $qty * $price;
        }

        return $totalQty > 0 ? $totalValue / $totalQty : 0.0;
    }

    /**
     * Crée une instance depuis un tableau.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderId: $data['order_id'],
            clientOrderId: $data['client_order_id'] ?? '',
            symbol: $data['symbol'],
            side: OrderSide::from($data['side']),
            type: OrderType::from($data['type']),
            status: OrderStatus::from($data['status']),
            quantity: (float) $data['quantity'],
            price: (float) $data['price'],
            quoteQuantity: (float) $data['quote_quantity'],
            commission: (float) ($data['commission'] ?? 0),
            commissionAsset: $data['commission_asset'] ?? 'USDT',
            executedAt: new DateTimeImmutable($data['executed_at']),
        );
    }

    /**
     * Convertit en tableau pour stockage.
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'client_order_id' => $this->clientOrderId,
            'symbol' => $this->symbol,
            'side' => $this->side->value,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'quote_quantity' => $this->quoteQuantity,
            'commission' => $this->commission,
            'commission_asset' => $this->commissionAsset,
            'executed_at' => $this->executedAt->format('c'),
        ];
    }

    /**
     * Calcule le montant total avec frais.
     */
    public function totalWithFees(): float
    {
        return $this->quoteQuantity + $this->commission;
    }
}
```

### 2. TradeStatsDTO

**Créer** : `app/DTOs/TradeStatsDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TradeStatsDTO
{
    public function __construct(
        public int $totalTrades,
        public int $buyCount,
        public int $sellCount,
        public int $winningTrades,
        public int $losingTrades,
        public float $winRate,
        public float $totalPnl,
        public float $totalPnlPercent,
        public float $averagePnl,
        public float $bestTrade,
        public float $worstTrade,
        public float $totalVolume,
        public float $totalFees,
    ) {}

    /**
     * Crée une instance avec les valeurs par défaut (aucun trade).
     */
    public static function empty(): self
    {
        return new self(
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            winningTrades: 0,
            losingTrades: 0,
            winRate: 0.0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            averagePnl: 0.0,
            bestTrade: 0.0,
            worstTrade: 0.0,
            totalVolume: 0.0,
            totalFees: 0.0,
        );
    }

    /**
     * Crée une instance depuis une collection de trades.
     */
    public static function fromTrades(array $trades, float $initialBalance = 0.0): self
    {
        if (empty($trades)) {
            return self::empty();
        }

        $buyCount = 0;
        $sellCount = 0;
        $winningTrades = 0;
        $losingTrades = 0;
        $totalPnl = 0.0;
        $totalVolume = 0.0;
        $totalFees = 0.0;
        $pnls = [];

        foreach ($trades as $trade) {
            $totalVolume += $trade['quote_quantity'] ?? 0;
            $totalFees += $trade['commission'] ?? 0;

            if (($trade['side'] ?? '') === 'BUY') {
                $buyCount++;
            } else {
                $sellCount++;
            }

            if (isset($trade['pnl'])) {
                $pnl = (float) $trade['pnl'];
                $totalPnl += $pnl;
                $pnls[] = $pnl;

                if ($pnl > 0) {
                    $winningTrades++;
                } elseif ($pnl < 0) {
                    $losingTrades++;
                }
            }
        }

        $totalTrades = count($trades);
        $tradesWithPnl = count($pnls);

        return new self(
            totalTrades: $totalTrades,
            buyCount: $buyCount,
            sellCount: $sellCount,
            winningTrades: $winningTrades,
            losingTrades: $losingTrades,
            winRate: $tradesWithPnl > 0 ? ($winningTrades / $tradesWithPnl) * 100 : 0.0,
            totalPnl: $totalPnl,
            totalPnlPercent: $initialBalance > 0 ? ($totalPnl / $initialBalance) * 100 : 0.0,
            averagePnl: $tradesWithPnl > 0 ? $totalPnl / $tradesWithPnl : 0.0,
            bestTrade: !empty($pnls) ? max($pnls) : 0.0,
            worstTrade: !empty($pnls) ? min($pnls) : 0.0,
            totalVolume: $totalVolume,
            totalFees: $totalFees,
        );
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'total_trades' => $this->totalTrades,
            'buy_count' => $this->buyCount,
            'sell_count' => $this->sellCount,
            'winning_trades' => $this->winningTrades,
            'losing_trades' => $this->losingTrades,
            'win_rate' => round($this->winRate, 2),
            'total_pnl' => round($this->totalPnl, 2),
            'total_pnl_percent' => round($this->totalPnlPercent, 2),
            'average_pnl' => round($this->averagePnl, 2),
            'best_trade' => round($this->bestTrade, 2),
            'worst_trade' => round($this->worstTrade, 2),
            'total_volume' => round($this->totalVolume, 2),
            'total_fees' => round($this->totalFees, 4),
        ];
    }
}
```

### 3. BalanceDTO

**Créer** : `app/DTOs/BalanceDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class BalanceDTO
{
    public function __construct(
        public string $asset,
        public float $free,
        public float $locked,
    ) {}

    /**
     * Crée une instance depuis une réponse Binance.
     */
    public static function fromBinanceResponse(array $data): self
    {
        return new self(
            asset: $data['asset'],
            free: (float) $data['free'],
            locked: (float) $data['locked'],
        );
    }

    /**
     * Retourne le solde total (libre + bloqué).
     */
    public function total(): float
    {
        return $this->free + $this->locked;
    }

    /**
     * Vérifie si le solde est vide.
     */
    public function isEmpty(): bool
    {
        return $this->total() <= 0;
    }

    /**
     * Vérifie si le solde est significatif (> seuil minimum).
     */
    public function isSignificant(float $minAmount = 0.00001): bool
    {
        return $this->total() > $minAmount;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'asset' => $this->asset,
            'free' => $this->free,
            'locked' => $this->locked,
            'total' => $this->total(),
        ];
    }
}
```

### 4. KlineDTO

**Créer** : `app/DTOs/KlineDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;

final readonly class KlineDTO
{
    public function __construct(
        public DateTimeImmutable $openTime,
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public float $volume,
        public DateTimeImmutable $closeTime,
        public float $quoteVolume,
        public int $numberOfTrades,
    ) {}

    /**
     * Crée une instance depuis une réponse Binance.
     *
     * Format Binance kline:
     * [0] Open time, [1] Open, [2] High, [3] Low, [4] Close, [5] Volume,
     * [6] Close time, [7] Quote asset volume, [8] Number of trades,
     * [9] Taker buy base volume, [10] Taker buy quote volume, [11] Ignore
     */
    public static function fromBinanceResponse(array $kline): self
    {
        return new self(
            openTime: (new DateTimeImmutable())->setTimestamp((int) ($kline[0] / 1000)),
            open: (float) $kline[1],
            high: (float) $kline[2],
            low: (float) $kline[3],
            close: (float) $kline[4],
            volume: (float) $kline[5],
            closeTime: (new DateTimeImmutable())->setTimestamp((int) ($kline[6] / 1000)),
            quoteVolume: (float) $kline[7],
            numberOfTrades: (int) $kline[8],
        );
    }

    /**
     * Retourne le prix médian.
     */
    public function medianPrice(): float
    {
        return ($this->high + $this->low) / 2;
    }

    /**
     * Retourne le prix typique (HLC/3).
     */
    public function typicalPrice(): float
    {
        return ($this->high + $this->low + $this->close) / 3;
    }

    /**
     * Vérifie si c'est un chandelier haussier.
     */
    public function isBullish(): bool
    {
        return $this->close > $this->open;
    }

    /**
     * Vérifie si c'est un chandelier baissier.
     */
    public function isBearish(): bool
    {
        return $this->close < $this->open;
    }

    /**
     * Retourne la variation en pourcentage.
     */
    public function changePercent(): float
    {
        if ($this->open === 0.0) {
            return 0.0;
        }

        return (($this->close - $this->open) / $this->open) * 100;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'open_time' => $this->openTime->format('c'),
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
            'close_time' => $this->closeTime->format('c'),
            'quote_volume' => $this->quoteVolume,
            'number_of_trades' => $this->numberOfTrades,
        ];
    }
}
```

### 5. IndicatorsDTO

**Créer** : `app/DTOs/IndicatorsDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class IndicatorsDTO
{
    public function __construct(
        public ?float $rsi = null,
        public ?float $ma50 = null,
        public ?float $ma200 = null,
        public ?float $currentPrice = null,
    ) {}

    /**
     * Vérifie si le RSI indique une survente.
     */
    public function isRsiOversold(float $threshold = 30.0): bool
    {
        return $this->rsi !== null && $this->rsi < $threshold;
    }

    /**
     * Vérifie si le RSI indique un surachat.
     */
    public function isRsiOverbought(float $threshold = 70.0): bool
    {
        return $this->rsi !== null && $this->rsi > $threshold;
    }

    /**
     * Vérifie si on est en Golden Cross (MA50 > MA200).
     */
    public function isGoldenCross(): bool
    {
        return $this->ma50 !== null
            && $this->ma200 !== null
            && $this->ma50 > $this->ma200;
    }

    /**
     * Vérifie si on est en Death Cross (MA50 < MA200).
     */
    public function isDeathCross(): bool
    {
        return $this->ma50 !== null
            && $this->ma200 !== null
            && $this->ma50 < $this->ma200;
    }

    /**
     * Vérifie si le prix est au-dessus de la MA50.
     */
    public function isPriceAboveMa50(): bool
    {
        return $this->currentPrice !== null
            && $this->ma50 !== null
            && $this->currentPrice > $this->ma50;
    }

    /**
     * Vérifie si le prix est au-dessus de la MA200.
     */
    public function isPriceAboveMa200(): bool
    {
        return $this->currentPrice !== null
            && $this->ma200 !== null
            && $this->currentPrice > $this->ma200;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return array_filter([
            'rsi' => $this->rsi !== null ? round($this->rsi, 2) : null,
            'ma50' => $this->ma50 !== null ? round($this->ma50, 2) : null,
            'ma200' => $this->ma200 !== null ? round($this->ma200, 2) : null,
            'current_price' => $this->currentPrice,
        ], fn ($value) => $value !== null);
    }

    /**
     * Crée une instance avec des indicateurs additionnels.
     */
    public function withRsi(float $rsi): self
    {
        return new self(
            rsi: $rsi,
            ma50: $this->ma50,
            ma200: $this->ma200,
            currentPrice: $this->currentPrice,
        );
    }

    /**
     * Crée une instance avec des moyennes mobiles.
     */
    public function withMovingAverages(float $ma50, float $ma200): self
    {
        return new self(
            rsi: $this->rsi,
            ma50: $ma50,
            ma200: $ma200,
            currentPrice: $this->currentPrice,
        );
    }

    /**
     * Crée une instance avec le prix actuel.
     */
    public function withCurrentPrice(float $price): self
    {
        return new self(
            rsi: $this->rsi,
            ma50: $this->ma50,
            ma200: $this->ma200,
            currentPrice: $price,
        );
    }
}
```

### 6. TradingResultDTO

**Créer** : `app/DTOs/TradingResultDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Signal;
use App\Enums\Strategy;

final readonly class TradingResultDTO
{
    public function __construct(
        public string $symbol,
        public Strategy $strategy,
        public Signal $signal,
        public IndicatorsDTO $indicators,
        public ?TradeResultDTO $trade = null,
        public ?string $reason = null,
    ) {}

    /**
     * Vérifie si un trade a été exécuté.
     */
    public function hasTraded(): bool
    {
        return $this->trade !== null;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'strategy' => $this->strategy->value,
            'signal' => $this->signal->value,
            'indicators' => $this->indicators->toArray(),
            'trade' => $this->trade?->toArray(),
            'reason' => $this->reason,
        ];
    }

    /**
     * Crée un résultat sans trade.
     */
    public static function noTrade(
        string $symbol,
        Strategy $strategy,
        Signal $signal,
        IndicatorsDTO $indicators,
        string $reason,
    ): self {
        return new self(
            symbol: $symbol,
            strategy: $strategy,
            signal: $signal,
            indicators: $indicators,
            trade: null,
            reason: $reason,
        );
    }

    /**
     * Crée un résultat avec trade.
     */
    public static function withTrade(
        string $symbol,
        Strategy $strategy,
        Signal $signal,
        IndicatorsDTO $indicators,
        TradeResultDTO $trade,
    ): self {
        return new self(
            symbol: $symbol,
            strategy: $strategy,
            signal: $signal,
            indicators: $indicators,
            trade: $trade,
            reason: null,
        );
    }
}
```

### 7. DailyReportDTO

**Créer** : `app/DTOs/DailyReportDTO.php`

```php
<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;

final readonly class DailyReportDTO
{
    /**
     * @param array<TradeResultDTO> $trades
     * @param array<BalanceDTO> $balances
     */
    public function __construct(
        public DateTimeImmutable $date,
        public TradeStatsDTO $stats,
        public array $trades,
        public array $balances,
        public float $totalBalanceUsdt,
        public ?float $previousDayBalanceUsdt = null,
    ) {}

    /**
     * Calcule la variation journalière en pourcentage.
     */
    public function dailyChangePercent(): ?float
    {
        if ($this->previousDayBalanceUsdt === null || $this->previousDayBalanceUsdt <= 0) {
            return null;
        }

        return (($this->totalBalanceUsdt - $this->previousDayBalanceUsdt)
            / $this->previousDayBalanceUsdt) * 100;
    }

    /**
     * Calcule la variation journalière absolue.
     */
    public function dailyChangeAbsolute(): ?float
    {
        if ($this->previousDayBalanceUsdt === null) {
            return null;
        }

        return $this->totalBalanceUsdt - $this->previousDayBalanceUsdt;
    }

    /**
     * Vérifie si la journée a été positive.
     */
    public function isPositiveDay(): bool
    {
        return $this->stats->totalPnl > 0;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'stats' => $this->stats->toArray(),
            'trades' => array_map(fn ($t) => $t->toArray(), $this->trades),
            'balances' => array_map(fn ($b) => $b->toArray(), $this->balances),
            'total_balance_usdt' => round($this->totalBalanceUsdt, 2),
            'previous_day_balance_usdt' => $this->previousDayBalanceUsdt !== null
                ? round($this->previousDayBalanceUsdt, 2)
                : null,
            'daily_change_percent' => $this->dailyChangePercent() !== null
                ? round($this->dailyChangePercent(), 2)
                : null,
            'daily_change_absolute' => $this->dailyChangeAbsolute() !== null
                ? round($this->dailyChangeAbsolute(), 2)
                : null,
        ];
    }
}
```

## Tests

**Créer** : `tests/Unit/DTOs/TradeResultDTOTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\TradeResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use PHPUnit\Framework\TestCase;

final class TradeResultDTOTest extends TestCase
{
    public function test_from_binance_response_creates_dto(): void
    {
        $response = [
            'orderId' => 12345,
            'clientOrderId' => 'test123',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'status' => 'FILLED',
            'executedQty' => '0.001',
            'cummulativeQuoteQty' => '42.50',
            'fills' => [
                ['price' => '42500.00', 'qty' => '0.001', 'commission' => '0.0425', 'commissionAsset' => 'USDT'],
            ],
        ];

        $dto = TradeResultDTO::fromBinanceResponse($response);

        $this->assertEquals('12345', $dto->orderId);
        $this->assertEquals('BTCUSDT', $dto->symbol);
        $this->assertEquals(OrderSide::Buy, $dto->side);
        $this->assertEquals(OrderStatus::Filled, $dto->status);
        $this->assertEquals(0.001, $dto->quantity);
        $this->assertEquals(42500.0, $dto->price);
        $this->assertEquals(0.0425, $dto->commission);
    }

    public function test_total_with_fees(): void
    {
        $dto = new TradeResultDTO(
            orderId: '123',
            clientOrderId: 'test',
            symbol: 'BTCUSDT',
            side: OrderSide::Buy,
            type: OrderType::Market,
            status: OrderStatus::Filled,
            quantity: 0.001,
            price: 42500.0,
            quoteQuantity: 42.50,
            commission: 0.05,
            commissionAsset: 'USDT',
            executedAt: new \DateTimeImmutable(),
        );

        $this->assertEquals(42.55, $dto->totalWithFees());
    }
}
```

## Dépendances

- **Prérequis** : Tâche 2.3 (Enums)
- **Utilisé par** : Tâches 2.5 (Models), 2.6 (BinanceService), 2.8 (TradingStrategy), 2.11 (ReportService)

## Checklist

- [ ] Créer `app/DTOs/TradeResultDTO.php`
- [ ] Créer `app/DTOs/TradeStatsDTO.php`
- [ ] Créer `app/DTOs/BalanceDTO.php`
- [ ] Créer `app/DTOs/KlineDTO.php`
- [ ] Créer `app/DTOs/IndicatorsDTO.php`
- [ ] Créer `app/DTOs/TradingResultDTO.php`
- [ ] Créer `app/DTOs/DailyReportDTO.php`
- [ ] Créer les tests unitaires pour les DTOs
- [ ] Vérifier avec `php artisan test --filter=DTOs`
- [ ] Vérifier avec `vendor/bin/pint`
