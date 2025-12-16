<?php

declare(strict_types=1);

namespace App\Services\Trading\Indicators;

use App\Contracts\IndicatorInterface;
use InvalidArgumentException;

/**
 * MA (Moving Average) - Moyenne Mobile Simple.
 *
 * La moyenne mobile lisse les données de prix pour identifier les tendances.
 * - MA courte (ex: 50) > MA longue (ex: 200) : tendance haussière (Golden Cross)
 * - MA courte < MA longue : tendance baissière (Death Cross)
 */
final class MovingAverageIndicator implements IndicatorInterface
{
    public function __construct(
        private readonly int $period = 50,
    ) {
        if ($this->period < 1) {
            throw new InvalidArgumentException('MA period must be at least 1');
        }
    }

    /**
     * Calcule la moyenne mobile simple (SMA) sur un tableau de prix.
     *
     * SMA = (Prix1 + Prix2 + ... + PrixN) / N
     *
     * @param  array<float>  $prices  Prix de clôture (close prices)
     * @return float Moyenne mobile
     */
    public function calculate(array $prices): float
    {
        $count = count($prices);

        if ($count < $this->period) {
            throw new InvalidArgumentException(
                "Insufficient data: need at least {$this->period} prices, got {$count}"
            );
        }

        // Prendre les N derniers prix
        $relevantPrices = array_slice($prices, -$this->period);

        // Calculer la moyenne
        $sum = array_sum($relevantPrices);
        $average = $sum / $this->period;

        return round($average, 2);
    }

    /**
     * Retourne le nom de l'indicateur.
     */
    public function getName(): string
    {
        return "MA({$this->period})";
    }

    /**
     * Retourne la période requise pour le calcul.
     */
    public function getRequiredPeriod(): int
    {
        return $this->period;
    }

    /**
     * Calcule la Moyenne Mobile Exponentielle (EMA).
     *
     * L'EMA donne plus de poids aux prix récents.
     * EMA = Prix * (2/(N+1)) + EMA_previous * (1 - (2/(N+1)))
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return float EMA
     */
    public function calculateEma(array $prices): float
    {
        $count = count($prices);

        if ($count < $this->period) {
            throw new InvalidArgumentException(
                "Insufficient data: need at least {$this->period} prices, got {$count}"
            );
        }

        // Calculer le multiplicateur
        $multiplier = 2 / ($this->period + 1);

        // Initialiser l'EMA avec la SMA des N premiers prix
        $sma = array_sum(array_slice($prices, 0, $this->period)) / $this->period;
        $ema = $sma;

        // Calculer l'EMA pour chaque prix suivant
        for ($i = $this->period; $i < $count; $i++) {
            $ema = ($prices[$i] * $multiplier) + ($ema * (1 - $multiplier));
        }

        return round($ema, 2);
    }

    /**
     * Détecte un Golden Cross (croisement haussier).
     *
     * Golden Cross : MA courte croise MA longue vers le haut
     *
     * @param  array<float>  $prices  Prix de clôture
     * @param  int  $shortPeriod  Période MA courte (ex: 50)
     * @param  int  $longPeriod  Période MA longue (ex: 200)
     * @return bool True si Golden Cross détecté
     */
    public static function detectGoldenCross(
        array $prices,
        int $shortPeriod = 50,
        int $longPeriod = 200
    ): bool {
        if (count($prices) < $longPeriod + 1) {
            return false;
        }

        // Calculer MA courte et longue actuelles
        $maShort = (new self($shortPeriod))->calculate($prices);
        $maLong = (new self($longPeriod))->calculate($prices);

        // Calculer MA courte et longue précédentes
        $previousPrices = array_slice($prices, 0, -1);
        $maShortPrev = (new self($shortPeriod))->calculate($previousPrices);
        $maLongPrev = (new self($longPeriod))->calculate($previousPrices);

        // Golden Cross : MA courte était en-dessous et passe au-dessus
        return $maShortPrev <= $maLongPrev && $maShort > $maLong;
    }

    /**
     * Détecte un Death Cross (croisement baissier).
     *
     * Death Cross : MA courte croise MA longue vers le bas
     *
     * @param  array<float>  $prices  Prix de clôture
     * @param  int  $shortPeriod  Période MA courte (ex: 50)
     * @param  int  $longPeriod  Période MA longue (ex: 200)
     * @return bool True si Death Cross détecté
     */
    public static function detectDeathCross(
        array $prices,
        int $shortPeriod = 50,
        int $longPeriod = 200
    ): bool {
        if (count($prices) < $longPeriod + 1) {
            return false;
        }

        // Calculer MA courte et longue actuelles
        $maShort = (new self($shortPeriod))->calculate($prices);
        $maLong = (new self($longPeriod))->calculate($prices);

        // Calculer MA courte et longue précédentes
        $previousPrices = array_slice($prices, 0, -1);
        $maShortPrev = (new self($shortPeriod))->calculate($previousPrices);
        $maLongPrev = (new self($longPeriod))->calculate($previousPrices);

        // Death Cross : MA courte était au-dessus et passe en-dessous
        return $maShortPrev >= $maLongPrev && $maShort < $maLong;
    }

    /**
     * Vérifie si la tendance est haussière (MA courte > MA longue).
     *
     * @param  array<float>  $prices  Prix de clôture
     * @param  int  $shortPeriod  Période MA courte
     * @param  int  $longPeriod  Période MA longue
     * @return bool True si tendance haussière
     */
    public static function isBullishTrend(
        array $prices,
        int $shortPeriod = 50,
        int $longPeriod = 200
    ): bool {
        if (count($prices) < $longPeriod) {
            return false;
        }

        $maShort = (new self($shortPeriod))->calculate($prices);
        $maLong = (new self($longPeriod))->calculate($prices);

        return $maShort > $maLong;
    }

    /**
     * Vérifie si la tendance est baissière (MA courte < MA longue).
     *
     * @param  array<float>  $prices  Prix de clôture
     * @param  int  $shortPeriod  Période MA courte
     * @param  int  $longPeriod  Période MA longue
     * @return bool True si tendance baissière
     */
    public static function isBearishTrend(
        array $prices,
        int $shortPeriod = 50,
        int $longPeriod = 200
    ): bool {
        if (count($prices) < $longPeriod) {
            return false;
        }

        $maShort = (new self($shortPeriod))->calculate($prices);
        $maLong = (new self($longPeriod))->calculate($prices);

        return $maShort < $maLong;
    }
}
