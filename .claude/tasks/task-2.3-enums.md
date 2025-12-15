# Task 2.3 - Enums (OrderSide, OrderType, OrderStatus, Strategy, Signal)

## Objectif

Créer les énumérations PHP 8.4 pour typer les constantes du domaine trading.

## Fichiers à créer

| Fichier | Description |
|---------|-------------|
| `app/Enums/OrderSide.php` | Direction de l'ordre (BUY/SELL) |
| `app/Enums/OrderType.php` | Type d'ordre (MARKET/LIMIT) |
| `app/Enums/OrderStatus.php` | Statut de l'ordre |
| `app/Enums/Strategy.php` | Stratégie de trading |
| `app/Enums/Signal.php` | Signal de trading (BUY/SELL/HOLD) |
| `app/Enums/KlineInterval.php` | Intervalles de chandeliers |

## Implémentation

### 1. OrderSide

**Créer** : `app/Enums/OrderSide.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSide: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
        };
    }

    /**
     * Retourne l'emoji correspondant.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::Buy => '🟢',
            self::Sell => '🔴',
        };
    }

    /**
     * Vérifie si c'est le côté opposé.
     */
    public function isOpposite(self $other): bool
    {
        return $this !== $other;
    }

    /**
     * Retourne le côté opposé.
     */
    public function opposite(): self
    {
        return match ($this) {
            self::Buy => self::Sell,
            self::Sell => self::Buy,
        };
    }
}
```

### 2. OrderType

**Créer** : `app/Enums/OrderType.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderType: string
{
    case Market = 'MARKET';
    case Limit = 'LIMIT';
    case StopLoss = 'STOP_LOSS';
    case StopLossLimit = 'STOP_LOSS_LIMIT';
    case TakeProfit = 'TAKE_PROFIT';
    case TakeProfitLimit = 'TAKE_PROFIT_LIMIT';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::Market => 'Market',
            self::Limit => 'Limit',
            self::StopLoss => 'Stop Loss',
            self::StopLossLimit => 'Stop Loss Limit',
            self::TakeProfit => 'Take Profit',
            self::TakeProfitLimit => 'Take Profit Limit',
        };
    }

    /**
     * Indique si l'ordre nécessite un prix.
     */
    public function requiresPrice(): bool
    {
        return match ($this) {
            self::Market => false,
            default => true,
        };
    }

    /**
     * Indique si c'est un ordre d'exécution immédiate.
     */
    public function isImmediate(): bool
    {
        return $this === self::Market;
    }
}
```

### 3. OrderStatus

**Créer** : `app/Enums/OrderStatus.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'NEW';
    case PartiallyFilled = 'PARTIALLY_FILLED';
    case Filled = 'FILLED';
    case Canceled = 'CANCELED';
    case PendingCancel = 'PENDING_CANCEL';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Error = 'ERROR';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'En attente',
            self::PartiallyFilled => 'Partiellement exécuté',
            self::Filled => 'Exécuté',
            self::Canceled => 'Annulé',
            self::PendingCancel => 'Annulation en cours',
            self::Rejected => 'Rejeté',
            self::Expired => 'Expiré',
            self::Error => 'Erreur',
        };
    }

    /**
     * Indique si le statut est final (l'ordre ne changera plus).
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Filled,
            self::Canceled,
            self::Rejected,
            self::Expired,
            self::Error,
        ], true);
    }

    /**
     * Indique si l'ordre a été exécuté (au moins partiellement).
     */
    public function isExecuted(): bool
    {
        return in_array($this, [
            self::PartiallyFilled,
            self::Filled,
        ], true);
    }

    /**
     * Indique si l'ordre est en cours de traitement.
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::New,
            self::PartiallyFilled,
            self::PendingCancel,
        ], true);
    }

    /**
     * Retourne la couleur CSS associée au statut.
     */
    public function color(): string
    {
        return match ($this) {
            self::Filled => 'green',
            self::PartiallyFilled => 'blue',
            self::New, self::PendingCancel => 'yellow',
            self::Canceled, self::Expired => 'gray',
            self::Rejected, self::Error => 'red',
        };
    }

    /**
     * Créer depuis une réponse Binance (lowercase).
     */
    public static function fromBinance(string $status): self
    {
        return self::from(strtoupper($status));
    }
}
```

### 4. Strategy

**Créer** : `app/Enums/Strategy.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum Strategy: string
{
    case Rsi = 'rsi';
    case MovingAverage = 'ma';
    case Combined = 'combined';

    /**
     * Retourne le nom complet de la stratégie.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::Rsi => 'RSI (Relative Strength Index)',
            self::MovingAverage => 'Moyennes Mobiles (MA50/MA200)',
            self::Combined => 'RSI + Moyennes Mobiles',
        };
    }

    /**
     * Retourne le nom court.
     */
    public function shortName(): string
    {
        return match ($this) {
            self::Rsi => 'RSI',
            self::MovingAverage => 'MA',
            self::Combined => 'RSI+MA',
        };
    }

    /**
     * Retourne la description de la stratégie.
     */
    public function description(): string
    {
        return match ($this) {
            self::Rsi => 'Achète quand RSI < 30 (survente), vend quand RSI > 70 (surachat)',
            self::MovingAverage => 'Achète au Golden Cross (MA50 > MA200), vend au Death Cross',
            self::Combined => 'Combine les signaux RSI et MA pour plus de confirmation',
        };
    }

    /**
     * Retourne les indicateurs requis pour cette stratégie.
     */
    public function requiredIndicators(): array
    {
        return match ($this) {
            self::Rsi => ['rsi'],
            self::MovingAverage => ['ma50', 'ma200'],
            self::Combined => ['rsi', 'ma50', 'ma200'],
        };
    }
}
```

### 5. Signal

**Créer** : `app/Enums/Signal.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum Signal: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';
    case Hold = 'HOLD';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
            self::Hold => 'Attente',
        };
    }

    /**
     * Retourne l'emoji correspondant.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::Buy => '🟢',
            self::Sell => '🔴',
            self::Hold => '⏸️',
        };
    }

    /**
     * Indique si le signal déclenche une action de trading.
     */
    public function isActionable(): bool
    {
        return $this !== self::Hold;
    }

    /**
     * Convertit le signal en OrderSide (si applicable).
     */
    public function toOrderSide(): ?OrderSide
    {
        return match ($this) {
            self::Buy => OrderSide::Buy,
            self::Sell => OrderSide::Sell,
            self::Hold => null,
        };
    }
}
```

### 6. KlineInterval

**Créer** : `app/Enums/KlineInterval.php`

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum KlineInterval: string
{
    case OneMinute = '1m';
    case ThreeMinutes = '3m';
    case FiveMinutes = '5m';
    case FifteenMinutes = '15m';
    case ThirtyMinutes = '30m';
    case OneHour = '1h';
    case TwoHours = '2h';
    case FourHours = '4h';
    case SixHours = '6h';
    case EightHours = '8h';
    case TwelveHours = '12h';
    case OneDay = '1d';
    case ThreeDays = '3d';
    case OneWeek = '1w';
    case OneMonth = '1M';

    /**
     * Retourne la durée en secondes.
     */
    public function toSeconds(): int
    {
        return match ($this) {
            self::OneMinute => 60,
            self::ThreeMinutes => 180,
            self::FiveMinutes => 300,
            self::FifteenMinutes => 900,
            self::ThirtyMinutes => 1800,
            self::OneHour => 3600,
            self::TwoHours => 7200,
            self::FourHours => 14400,
            self::SixHours => 21600,
            self::EightHours => 28800,
            self::TwelveHours => 43200,
            self::OneDay => 86400,
            self::ThreeDays => 259200,
            self::OneWeek => 604800,
            self::OneMonth => 2592000,
        };
    }

    /**
     * Retourne le libellé lisible.
     */
    public function label(): string
    {
        return match ($this) {
            self::OneMinute => '1 minute',
            self::ThreeMinutes => '3 minutes',
            self::FiveMinutes => '5 minutes',
            self::FifteenMinutes => '15 minutes',
            self::ThirtyMinutes => '30 minutes',
            self::OneHour => '1 heure',
            self::TwoHours => '2 heures',
            self::FourHours => '4 heures',
            self::SixHours => '6 heures',
            self::EightHours => '8 heures',
            self::TwelveHours => '12 heures',
            self::OneDay => '1 jour',
            self::ThreeDays => '3 jours',
            self::OneWeek => '1 semaine',
            self::OneMonth => '1 mois',
        };
    }

    /**
     * Retourne l'intervalle par défaut pour le bot.
     */
    public static function default(): self
    {
        return self::FiveMinutes;
    }
}
```

## Utilisation

### Dans les Services

```php
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\Signal;
use App\Enums\Strategy;

// Vérifier un signal
if ($signal->isActionable()) {
    $side = $signal->toOrderSide();
}

// Vérifier un statut
if ($status->isFinal()) {
    // L'ordre ne changera plus
}

// Obtenir les indicateurs requis
$indicators = Strategy::Rsi->requiredIndicators();
```

### Dans les Form Requests

```php
use App\Enums\Strategy;
use Illuminate\Validation\Rule;

$request->validate([
    'strategy' => ['required', Rule::enum(Strategy::class)],
    'side' => ['required', Rule::enum(OrderSide::class)],
]);
```

### Dans les vues Blade

```blade
<span class="badge bg-{{ $trade->status->color() }}">
    {{ $trade->status->label() }}
</span>

<span>{{ $trade->side->emoji() }} {{ $trade->side->label() }}</span>
```

## Tests

**Créer** : `tests/Unit/Enums/OrderStatusTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    public function test_filled_status_is_final(): void
    {
        $this->assertTrue(OrderStatus::Filled->isFinal());
    }

    public function test_new_status_is_not_final(): void
    {
        $this->assertFalse(OrderStatus::New->isFinal());
    }

    public function test_filled_status_is_executed(): void
    {
        $this->assertTrue(OrderStatus::Filled->isExecuted());
    }

    public function test_from_binance_converts_lowercase(): void
    {
        $status = OrderStatus::fromBinance('filled');
        $this->assertEquals(OrderStatus::Filled, $status);
    }
}
```

**Créer** : `tests/Unit/Enums/SignalTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderSide;
use App\Enums\Signal;
use PHPUnit\Framework\TestCase;

final class SignalTest extends TestCase
{
    public function test_buy_signal_is_actionable(): void
    {
        $this->assertTrue(Signal::Buy->isActionable());
    }

    public function test_hold_signal_is_not_actionable(): void
    {
        $this->assertFalse(Signal::Hold->isActionable());
    }

    public function test_buy_signal_converts_to_order_side(): void
    {
        $this->assertEquals(OrderSide::Buy, Signal::Buy->toOrderSide());
    }

    public function test_hold_signal_returns_null_order_side(): void
    {
        $this->assertNull(Signal::Hold->toOrderSide());
    }
}
```

## Dépendances

- **Prérequis** : Tâche 2.1 (Laravel setup), Tâche 2.2 (Configuration)
- **Utilisé par** : Tâches 2.4 (DTOs), 2.5 (Models), 2.6 (BinanceService), 2.8 (TradingStrategy)

## Checklist

- [ ] Créer `app/Enums/OrderSide.php`
- [ ] Créer `app/Enums/OrderType.php`
- [ ] Créer `app/Enums/OrderStatus.php`
- [ ] Créer `app/Enums/Strategy.php`
- [ ] Créer `app/Enums/Signal.php`
- [ ] Créer `app/Enums/KlineInterval.php`
- [ ] Créer les tests unitaires pour les enums
- [ ] Vérifier avec `php artisan test --filter=Enums`
- [ ] Vérifier avec `vendor/bin/pint`
