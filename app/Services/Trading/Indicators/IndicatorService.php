<?php

declare(strict_types=1);

namespace App\Services\Trading\Indicators;

use App\DTOs\IndicatorsDTO;
use App\DTOs\KlineDTO;
use InvalidArgumentException;

/**
 * Service de coordination pour calculer plusieurs indicateurs techniques.
 */
final class IndicatorService
{
    public function __construct(
        private readonly RsiIndicator $rsiIndicator,
        private readonly MovingAverageIndicator $ma50Indicator,
        private readonly MovingAverageIndicator $ma200Indicator,
    ) {}

    /**
     * Calcule tous les indicateurs depuis des données kline.
     *
     * @param  array<KlineDTO>  $klines  Données chandelier
     * @return IndicatorsDTO Tous les indicateurs calculés
     */
    public function calculateFromKlines(array $klines): IndicatorsDTO
    {
        if (empty($klines)) {
            throw new InvalidArgumentException('Klines array cannot be empty');
        }

        // Extraire les prix de clôture
        $closePrices = array_map(fn (KlineDTO $kline) => $kline->close, $klines);

        return $this->calculate($closePrices);
    }

    /**
     * Calcule tous les indicateurs depuis un tableau de prix.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return IndicatorsDTO Tous les indicateurs calculés
     */
    public function calculate(array $prices): IndicatorsDTO
    {
        if (empty($prices)) {
            throw new InvalidArgumentException('Prices array cannot be empty');
        }

        $indicators = [];

        // Calculer RSI si suffisamment de données
        try {
            $rsi = $this->rsiIndicator->calculate($prices);
            $indicators['rsi'] = $rsi;
            $indicators['rsi_signal'] = $this->rsiIndicator->interpret($rsi);
        } catch (InvalidArgumentException $e) {
            $indicators['rsi'] = null;
            $indicators['rsi_signal'] = 'insufficient_data';
        }

        // Calculer MA50 si suffisamment de données
        try {
            $ma50 = $this->ma50Indicator->calculate($prices);
            $indicators['ma50'] = $ma50;
        } catch (InvalidArgumentException $e) {
            $indicators['ma50'] = null;
        }

        // Calculer MA200 si suffisamment de données
        try {
            $ma200 = $this->ma200Indicator->calculate($prices);
            $indicators['ma200'] = $ma200;
        } catch (InvalidArgumentException $e) {
            $indicators['ma200'] = null;
        }

        // Déterminer la tendance si les deux MA sont disponibles
        if ($indicators['ma50'] !== null && $indicators['ma200'] !== null) {
            if ($indicators['ma50'] > $indicators['ma200']) {
                $indicators['trend'] = 'bullish';
            } elseif ($indicators['ma50'] < $indicators['ma200']) {
                $indicators['trend'] = 'bearish';
            } else {
                $indicators['trend'] = 'neutral';
            }

            // Détecter les croisements
            $indicators['golden_cross'] = MovingAverageIndicator::detectGoldenCross($prices, 50, 200);
            $indicators['death_cross'] = MovingAverageIndicator::detectDeathCross($prices, 50, 200);
        } else {
            $indicators['trend'] = 'unknown';
            $indicators['golden_cross'] = false;
            $indicators['death_cross'] = false;
        }

        return IndicatorsDTO::fromArray($indicators);
    }

    /**
     * Calcule uniquement le RSI.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return float RSI
     */
    public function calculateRsi(array $prices): float
    {
        return $this->rsiIndicator->calculate($prices);
    }

    /**
     * Calcule uniquement la MA50.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return float MA50
     */
    public function calculateMa50(array $prices): float
    {
        return $this->ma50Indicator->calculate($prices);
    }

    /**
     * Calcule uniquement la MA200.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return float MA200
     */
    public function calculateMa200(array $prices): float
    {
        return $this->ma200Indicator->calculate($prices);
    }

    /**
     * Vérifie si un signal d'achat RSI est détecté.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @param  float  $threshold  Seuil de survente (défaut: 30)
     * @return bool True si signal d'achat
     */
    public function isRsiBuySignal(array $prices, float $threshold = 30.0): bool
    {
        try {
            $rsi = $this->rsiIndicator->calculate($prices);

            return $this->rsiIndicator->isOversold($rsi, $threshold);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Vérifie si un signal de vente RSI est détecté.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @param  float  $threshold  Seuil de surachat (défaut: 70)
     * @return bool True si signal de vente
     */
    public function isRsiSellSignal(array $prices, float $threshold = 70.0): bool
    {
        try {
            $rsi = $this->rsiIndicator->calculate($prices);

            return $this->rsiIndicator->isOverbought($rsi, $threshold);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Vérifie si un Golden Cross est détecté.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return bool True si Golden Cross
     */
    public function isGoldenCross(array $prices): bool
    {
        return MovingAverageIndicator::detectGoldenCross($prices, 50, 200);
    }

    /**
     * Vérifie si un Death Cross est détecté.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return bool True si Death Cross
     */
    public function isDeathCross(array $prices): bool
    {
        return MovingAverageIndicator::detectDeathCross($prices, 50, 200);
    }

    /**
     * Vérifie si la tendance est haussière.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return bool True si tendance haussière
     */
    public function isBullishTrend(array $prices): bool
    {
        return MovingAverageIndicator::isBullishTrend($prices, 50, 200);
    }

    /**
     * Vérifie si la tendance est baissière.
     *
     * @param  array<float>  $prices  Prix de clôture
     * @return bool True si tendance baissière
     */
    public function isBearishTrend(array $prices): bool
    {
        return MovingAverageIndicator::isBearishTrend($prices, 50, 200);
    }
}
