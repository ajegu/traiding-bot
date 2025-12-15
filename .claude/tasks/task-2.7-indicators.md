# Task 2.7 - Indicateurs Techniques (RSI, MA50, MA200)

## Objectif

Créer les calculateurs d'indicateurs techniques : RSI (Relative Strength Index) et Moyennes Mobiles (MA50, MA200).

## Fichiers à créer

| Fichier | Description |
|---------|-------------|
| `app/Contracts/IndicatorInterface.php` | Interface commune des indicateurs |
| `app/Services/Trading/Indicators/RsiIndicator.php` | Calculateur RSI |
| `app/Services/Trading/Indicators/MovingAverageIndicator.php` | Calculateur MA |
| `app/Services/Trading/Indicators/IndicatorService.php` | Service coordinateur |

## Théorie des Indicateurs

### RSI (Relative Strength Index)

Le RSI mesure la vitesse et l'amplitude des mouvements de prix.

**Formule :**
```
RSI = 100 - (100 / (1 + RS))
RS = Average Gain / Average Loss
```

**Interprétation :**
- RSI < 30 : Zone de survente (signal d'achat potentiel)
- RSI > 70 : Zone de surachat (signal de vente potentiel)
- RSI = 50 : Équilibre

### Moyennes Mobiles (MA)

La moyenne mobile lisse les données de prix sur une période.

**Types :**
- SMA (Simple Moving Average) : Moyenne arithmétique simple
- EMA (Exponential Moving Average) : Pondération exponentielle (plus récent = plus important)

**Signaux :**
- Golden Cross : MA courte (50) croise MA longue (200) vers le haut → Signal d'achat
- Death Cross : MA courte (50) croise MA longue (200) vers le bas → Signal de vente

## Implémentation

### 1. Interface IndicatorInterface

**Créer** : `app/Contracts/IndicatorInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

interface IndicatorInterface
{
    /**
     * Calcule la valeur de l'indicateur.
     *
     * @param array<float> $prices Liste des prix de clôture (du plus ancien au plus récent)
     * @return float Valeur de l'indicateur
     */
    public function calculate(array $prices): float;

    /**
     * Retourne le nombre minimum de données requises.
     */
    public function getMinimumDataPoints(): int;

    /**
     * Retourne le nom de l'indicateur.
     */
    public function getName(): string;
}
```

### 2. RsiIndicator

**Créer** : `app/Services/Trading/Indicators/RsiIndicator.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Trading\Indicators;

use App\Contracts\IndicatorInterface;
use InvalidArgumentException;

final class RsiIndicator implements IndicatorInterface
{
    private const DEFAULT_PERIOD = 14;

    public function __construct(
        private readonly int $period = self::DEFAULT_PERIOD,
    ) {
        if ($period < 2) {
            throw new InvalidArgumentException('RSI period must be at least 2');
        }
    }

    public function calculate(array $prices): float
    {
        $minDataPoints = $this->getMinimumDataPoints();

        if (count($prices) < $minDataPoints) {
            throw new InvalidArgumentException(
                "RSI requires at least {$minDataPoints} data points, " . count($prices) . ' provided'
            );
        }

        // Calculer les variations de prix
        $changes = $this->calculatePriceChanges($prices);

        // Séparer les gains et les pertes
        $gains = [];
        $losses = [];

        foreach ($changes as $change) {
            if ($change > 0) {
                $gains[] = $change;
                $losses[] = 0.0;
            } elseif ($change < 0) {
                $gains[] = 0.0;
                $losses[] = abs($change);
            } else {
                $gains[] = 0.0;
                $losses[] = 0.0;
            }
        }

        // Calculer les moyennes avec lissage exponentiel (Wilder's smoothing)
        $avgGain = $this->calculateSmoothedAverage($gains, $this->period);
        $avgLoss = $this->calculateSmoothedAverage($losses, $this->period);

        // Éviter la division par zéro
        if ($avgLoss === 0.0) {
            return 100.0; // Que des gains = RSI max
        }

        // Calculer RS et RSI
        $rs = $avgGain / $avgLoss;
        $rsi = 100.0 - (100.0 / (1.0 + $rs));

        return round($rsi, 2);
    }

    public function getMinimumDataPoints(): int
    {
        // Période + 1 pour avoir assez de variations
        return $this->period + 1;
    }

    public function getName(): string
    {
        return "RSI({$this->period})";
    }

    /**
     * Calcule les variations de prix entre chaque période.
     *
     * @param array<float> $prices
     * @return array<float>
     */
    private function calculatePriceChanges(array $prices): array
    {
        $changes = [];

        for ($i = 1; $i < count($prices); $i++) {
            $changes[] = $prices[$i] - $prices[$i - 1];
        }

        return $changes;
    }

    /**
     * Calcule la moyenne lissée (Wilder's smoothing method).
     *
     * @param array<float> $values
     * @param int $period
     * @return float
     */
    private function calculateSmoothedAverage(array $values, int $period): float
    {
        if (count($values) < $period) {
            return array_sum($values) / count($values);
        }

        // Première moyenne : SMA sur la première période
        $firstAvg = array_sum(array_slice($values, 0, $period)) / $period;

        // Appliquer le lissage exponentiel sur le reste
        $smoothed = $firstAvg;

        for ($i = $period; $i < count($values); $i++) {
            // Wilder's smoothing: new_avg = (prev_avg * (period - 1) + current_value) / period
            $smoothed = (($smoothed * ($period - 1)) + $values[$i]) / $period;
        }

        return $smoothed;
    }
}
```

### 3. MovingAverageIndicator

**Créer** : `app/Services/Trading/Indicators/MovingAverageIndicator.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Trading\Indicators;

use App\Contracts\IndicatorInterface;
use InvalidArgumentException;

final class MovingAverageIndicator implements IndicatorInterface
{
    public const TYPE_SMA = 'sma';
    public const TYPE_EMA = 'ema';

    public function __construct(
        private readonly int $period,
        private readonly string $type = self::TYPE_SMA,
    ) {
        if ($period < 1) {
            throw new InvalidArgumentException('MA period must be at least 1');
        }

        if (!in_array($type, [self::TYPE_SMA, self::TYPE_EMA], true)) {
            throw new InvalidArgumentException("Invalid MA type: {$type}");
        }
    }

    public function calculate(array $prices): float
    {
        if (count($prices) < $this->getMinimumDataPoints()) {
            throw new InvalidArgumentException(
                "MA({$this->period}) requires at least {$this->period} data points, " . count($prices) . ' provided'
            );
        }

        return match ($this->type) {
            self::TYPE_SMA => $this->calculateSMA($prices),
            self::TYPE_EMA => $this->calculateEMA($prices),
        };
    }

    public function getMinimumDataPoints(): int
    {
        return $this->period;
    }

    public function getName(): string
    {
        $typeName = strtoupper($this->type);
        return "{$typeName}({$this->period})";
    }

    /**
     * Calcule la moyenne mobile simple (SMA).
     *
     * @param array<float> $prices
     * @return float
     */
    private function calculateSMA(array $prices): float
    {
        // Prendre les N derniers prix
        $subset = array_slice($prices, -$this->period);

        return round(array_sum($subset) / count($subset), 2);
    }

    /**
     * Calcule la moyenne mobile exponentielle (EMA).
     *
     * @param array<float> $prices
     * @return float
     */
    private function calculateEMA(array $prices): float
    {
        // Multiplicateur : 2 / (période + 1)
        $multiplier = 2.0 / ($this->period + 1);

        // Commencer avec une SMA pour la première période
        $ema = array_sum(array_slice($prices, 0, $this->period)) / $this->period;

        // Appliquer le calcul EMA sur le reste des prix
        for ($i = $this->period; $i < count($prices); $i++) {
            // EMA = (Prix actuel - EMA précédent) * multiplicateur + EMA précédent
            $ema = (($prices[$i] - $ema) * $multiplier) + $ema;
        }

        return round($ema, 2);
    }

    /**
     * Crée un indicateur MA50.
     */
    public static function ma50(string $type = self::TYPE_SMA): self
    {
        return new self(50, $type);
    }

    /**
     * Crée un indicateur MA200.
     */
    public static function ma200(string $type = self::TYPE_SMA): self
    {
        return new self(200, $type);
    }
}
```

### 4. IndicatorService

**Créer** : `app/Services/Trading/Indicators/IndicatorService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Trading\Indicators;

use App\Contracts\BinanceServiceInterface;
use App\DTOs\IndicatorsDTO;
use App\DTOs\KlineDTO;
use App\Enums\KlineInterval;
use App\Enums\Strategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class IndicatorService
{
    private readonly RsiIndicator $rsiIndicator;
    private readonly MovingAverageIndicator $ma50Indicator;
    private readonly MovingAverageIndicator $ma200Indicator;

    public function __construct(
        private readonly BinanceServiceInterface $binance,
    ) {
        $this->rsiIndicator = new RsiIndicator(config('bot.strategy.rsi.period', 14));
        $this->ma50Indicator = MovingAverageIndicator::ma50();
        $this->ma200Indicator = MovingAverageIndicator::ma200();
    }

    /**
     * Calcule tous les indicateurs requis pour une stratégie.
     */
    public function calculateForStrategy(
        string $symbol,
        Strategy $strategy,
        KlineInterval $interval = KlineInterval::FiveMinutes,
    ): IndicatorsDTO {
        // Récupérer les klines
        $klines = $this->binance->getKlines($symbol, $interval, 300);
        $prices = $this->extractClosePrices($klines);
        $currentPrice = $this->binance->getCurrentPrice($symbol);

        $indicators = new IndicatorsDTO(currentPrice: $currentPrice);

        // Calculer selon la stratégie
        $requiredIndicators = $strategy->requiredIndicators();

        if (in_array('rsi', $requiredIndicators, true)) {
            $indicators = $indicators->withRsi($this->calculateRsi($prices));
        }

        if (in_array('ma50', $requiredIndicators, true) || in_array('ma200', $requiredIndicators, true)) {
            $indicators = $indicators->withMovingAverages(
                $this->calculateMa50($prices),
                $this->calculateMa200($prices),
            );
        }

        Log::info('Indicators calculated', [
            'symbol' => $symbol,
            'strategy' => $strategy->value,
            'indicators' => $indicators->toArray(),
        ]);

        return $indicators;
    }

    /**
     * Calcule le RSI.
     *
     * @param array<float> $prices
     */
    public function calculateRsi(array $prices): float
    {
        return $this->rsiIndicator->calculate($prices);
    }

    /**
     * Calcule la MA50.
     *
     * @param array<float> $prices
     */
    public function calculateMa50(array $prices): float
    {
        return $this->ma50Indicator->calculate($prices);
    }

    /**
     * Calcule la MA200.
     *
     * @param array<float> $prices
     */
    public function calculateMa200(array $prices): float
    {
        return $this->ma200Indicator->calculate($prices);
    }

    /**
     * Calcule tous les indicateurs disponibles.
     *
     * @param array<float> $prices
     */
    public function calculateAll(array $prices, float $currentPrice): IndicatorsDTO
    {
        $rsi = count($prices) >= $this->rsiIndicator->getMinimumDataPoints()
            ? $this->calculateRsi($prices)
            : null;

        $ma50 = count($prices) >= $this->ma50Indicator->getMinimumDataPoints()
            ? $this->calculateMa50($prices)
            : null;

        $ma200 = count($prices) >= $this->ma200Indicator->getMinimumDataPoints()
            ? $this->calculateMa200($prices)
            : null;

        return new IndicatorsDTO(
            rsi: $rsi,
            ma50: $ma50,
            ma200: $ma200,
            currentPrice: $currentPrice,
        );
    }

    /**
     * Extrait les prix de clôture des klines.
     *
     * @param Collection<int, KlineDTO> $klines
     * @return array<float>
     */
    private function extractClosePrices(Collection $klines): array
    {
        return $klines
            ->map(fn (KlineDTO $kline) => $kline->close)
            ->values()
            ->toArray();
    }
}
```

## Tests

### Test RSI

**Créer** : `tests/Unit/Services/Trading/Indicators/RsiIndicatorTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Trading\Indicators;

use App\Services\Trading\Indicators\RsiIndicator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RsiIndicatorTest extends TestCase
{
    public function test_calculate_rsi_with_upward_trend(): void
    {
        $indicator = new RsiIndicator(14);

        // Prix en hausse constante
        $prices = [];
        $price = 100.0;
        for ($i = 0; $i <= 20; $i++) {
            $prices[] = $price;
            $price += 1.0;
        }

        $rsi = $indicator->calculate($prices);

        // RSI devrait être élevé (> 70) pour une tendance haussière
        $this->assertGreaterThan(70, $rsi);
    }

    public function test_calculate_rsi_with_downward_trend(): void
    {
        $indicator = new RsiIndicator(14);

        // Prix en baisse constante
        $prices = [];
        $price = 120.0;
        for ($i = 0; $i <= 20; $i++) {
            $prices[] = $price;
            $price -= 1.0;
        }

        $rsi = $indicator->calculate($prices);

        // RSI devrait être faible (< 30) pour une tendance baissière
        $this->assertLessThan(30, $rsi);
    }

    public function test_calculate_rsi_with_sideways_trend(): void
    {
        $indicator = new RsiIndicator(14);

        // Prix oscillant
        $prices = [];
        for ($i = 0; $i <= 30; $i++) {
            $prices[] = 100.0 + ($i % 2 === 0 ? 1.0 : -1.0);
        }

        $rsi = $indicator->calculate($prices);

        // RSI devrait être proche de 50 pour un marché latéral
        $this->assertGreaterThan(40, $rsi);
        $this->assertLessThan(60, $rsi);
    }

    public function test_throws_exception_with_insufficient_data(): void
    {
        $indicator = new RsiIndicator(14);

        $this->expectException(InvalidArgumentException::class);

        $indicator->calculate([100, 101, 102]); // Seulement 3 points
    }

    public function test_minimum_data_points(): void
    {
        $indicator = new RsiIndicator(14);

        $this->assertEquals(15, $indicator->getMinimumDataPoints());
    }

    public function test_name(): void
    {
        $indicator = new RsiIndicator(14);

        $this->assertEquals('RSI(14)', $indicator->getName());
    }
}
```

### Test Moving Average

**Créer** : `tests/Unit/Services/Trading/Indicators/MovingAverageIndicatorTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Trading\Indicators;

use App\Services\Trading\Indicators\MovingAverageIndicator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MovingAverageIndicatorTest extends TestCase
{
    public function test_calculate_sma(): void
    {
        $indicator = new MovingAverageIndicator(5, MovingAverageIndicator::TYPE_SMA);

        $prices = [10.0, 20.0, 30.0, 40.0, 50.0];

        $sma = $indicator->calculate($prices);

        // SMA(5) = (10 + 20 + 30 + 40 + 50) / 5 = 30
        $this->assertEquals(30.0, $sma);
    }

    public function test_calculate_sma_uses_last_n_prices(): void
    {
        $indicator = new MovingAverageIndicator(3, MovingAverageIndicator::TYPE_SMA);

        $prices = [10.0, 20.0, 30.0, 40.0, 50.0];

        $sma = $indicator->calculate($prices);

        // SMA(3) des 3 derniers = (30 + 40 + 50) / 3 = 40
        $this->assertEquals(40.0, $sma);
    }

    public function test_calculate_ema(): void
    {
        $indicator = new MovingAverageIndicator(5, MovingAverageIndicator::TYPE_EMA);

        $prices = [10.0, 20.0, 30.0, 40.0, 50.0, 60.0, 70.0];

        $ema = $indicator->calculate($prices);

        // EMA donne plus de poids aux prix récents
        // La valeur exacte dépend du calcul, vérifions qu'elle est raisonnable
        $this->assertGreaterThan(40, $ema);
        $this->assertLessThan(70, $ema);
    }

    public function test_ema_is_more_responsive_than_sma(): void
    {
        $smaIndicator = new MovingAverageIndicator(10, MovingAverageIndicator::TYPE_SMA);
        $emaIndicator = new MovingAverageIndicator(10, MovingAverageIndicator::TYPE_EMA);

        // Prix stable puis forte hausse
        $prices = [100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 150.0, 150.0];

        $sma = $smaIndicator->calculate($prices);
        $ema = $emaIndicator->calculate($prices);

        // EMA devrait être plus élevé car il réagit plus vite à la hausse récente
        $this->assertGreaterThan($sma, $ema);
    }

    public function test_throws_exception_with_insufficient_data(): void
    {
        $indicator = new MovingAverageIndicator(50);

        $this->expectException(InvalidArgumentException::class);

        $indicator->calculate(array_fill(0, 30, 100.0)); // Seulement 30 points
    }

    public function test_factory_methods(): void
    {
        $ma50 = MovingAverageIndicator::ma50();
        $ma200 = MovingAverageIndicator::ma200();

        $this->assertEquals('SMA(50)', $ma50->getName());
        $this->assertEquals('SMA(200)', $ma200->getName());
    }
}
```

## Utilisation

```php
use App\Services\Trading\Indicators\IndicatorService;
use App\Enums\Strategy;

class TradingStrategy
{
    public function __construct(
        private readonly IndicatorService $indicatorService,
    ) {}

    public function analyze(string $symbol): void
    {
        // Calculer les indicateurs selon la stratégie
        $indicators = $this->indicatorService->calculateForStrategy(
            symbol: $symbol,
            strategy: Strategy::Combined,
        );

        // Utiliser les indicateurs
        if ($indicators->isRsiOversold()) {
            // Signal d'achat potentiel
        }

        if ($indicators->isGoldenCross()) {
            // Tendance haussière
        }
    }
}
```

## Dépendances

- **Prérequis** : Tâches 2.4 (DTOs), 2.6 (BinanceService)
- **Utilisé par** : Tâche 2.8 (TradingStrategy)

## Checklist

- [ ] Créer `app/Contracts/IndicatorInterface.php`
- [ ] Créer `app/Services/Trading/Indicators/RsiIndicator.php`
- [ ] Créer `app/Services/Trading/Indicators/MovingAverageIndicator.php`
- [ ] Créer `app/Services/Trading/Indicators/IndicatorService.php`
- [ ] Créer `tests/Unit/Services/Trading/Indicators/RsiIndicatorTest.php`
- [ ] Créer `tests/Unit/Services/Trading/Indicators/MovingAverageIndicatorTest.php`
- [ ] Vérifier avec `php artisan test --filter=Indicators`
- [ ] Vérifier avec `vendor/bin/pint`
