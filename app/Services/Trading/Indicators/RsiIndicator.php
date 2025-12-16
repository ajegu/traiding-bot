<?php

declare(strict_types=1);

namespace App\Services\Trading\Indicators;

use App\Contracts\IndicatorInterface;
use InvalidArgumentException;

/**
 * RSI (Relative Strength Index) - Indicateur de force relative.
 *
 * Le RSI mesure la vitesse et l'amplitude des mouvements de prix.
 * Valeur entre 0 et 100.
 * - RSI > 70 : surachat (overbought)
 * - RSI < 30 : survente (oversold)
 */
final class RsiIndicator implements IndicatorInterface
{
    public function __construct(
        private readonly int $period = 14,
    ) {
        if ($this->period < 2) {
            throw new InvalidArgumentException('RSI period must be at least 2');
        }
    }

    /**
     * Calcule le RSI sur un tableau de prix.
     *
     * Algorithme :
     * 1. Calculer les variations de prix (gains et pertes)
     * 2. Calculer la moyenne mobile des gains sur N périodes
     * 3. Calculer la moyenne mobile des pertes sur N périodes
     * 4. RS = Average Gain / Average Loss
     * 5. RSI = 100 - (100 / (1 + RS))
     *
     * @param  array<float>  $prices  Prix de clôture (close prices)
     * @return float RSI entre 0 et 100
     */
    public function calculate(array $prices): float
    {
        $count = count($prices);

        if ($count < $this->period + 1) {
            throw new InvalidArgumentException(
                "Insufficient data: need at least ".($this->period + 1)." prices, got {$count}"
            );
        }

        // Calculer les variations de prix
        $changes = [];
        for ($i = 1; $i < $count; $i++) {
            $changes[] = $prices[$i] - $prices[$i - 1];
        }

        // Séparer gains et pertes
        $gains = [];
        $losses = [];

        foreach ($changes as $change) {
            $gains[] = $change > 0 ? $change : 0;
            $losses[] = $change < 0 ? abs($change) : 0;
        }

        // Calculer les moyennes initiales (SMA sur les N premières périodes)
        $avgGain = array_sum(array_slice($gains, 0, $this->period)) / $this->period;
        $avgLoss = array_sum(array_slice($losses, 0, $this->period)) / $this->period;

        // Calculer les moyennes mobiles exponentielles pour les périodes suivantes
        for ($i = $this->period; $i < count($gains); $i++) {
            $avgGain = (($avgGain * ($this->period - 1)) + $gains[$i]) / $this->period;
            $avgLoss = (($avgLoss * ($this->period - 1)) + $losses[$i]) / $this->period;
        }

        // Éviter la division par zéro
        if ($avgLoss === 0.0) {
            return 100.0; // Tous les mouvements sont des gains
        }

        // Calculer le RS et le RSI
        $rs = $avgGain / $avgLoss;
        $rsi = 100 - (100 / (1 + $rs));

        return round($rsi, 2);
    }

    /**
     * Retourne le nom de l'indicateur.
     */
    public function getName(): string
    {
        return "RSI({$this->period})";
    }

    /**
     * Retourne la période requise pour le calcul.
     */
    public function getRequiredPeriod(): int
    {
        return $this->period + 1;
    }

    /**
     * Interprète la valeur du RSI.
     */
    public function interpret(float $rsi): string
    {
        if ($rsi >= 70) {
            return 'overbought'; // Surachat - signal de vente potentiel
        }

        if ($rsi <= 30) {
            return 'oversold'; // Survente - signal d'achat potentiel
        }

        return 'neutral';
    }

    /**
     * Vérifie si le RSI indique une survente (signal d'achat).
     */
    public function isOversold(float $rsi, float $threshold = 30.0): bool
    {
        return $rsi < $threshold;
    }

    /**
     * Vérifie si le RSI indique un surachat (signal de vente).
     */
    public function isOverbought(float $rsi, float $threshold = 70.0): bool
    {
        return $rsi > $threshold;
    }
}
