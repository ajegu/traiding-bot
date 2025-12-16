<?php

declare(strict_types=1);

namespace App\Services\Trading\Strategies;

use App\Contracts\TradingStrategyInterface;
use App\DTOs\IndicatorsDTO;
use App\Enums\Signal;
use Illuminate\Support\Facades\Log;

/**
 * Stratégie de trading basée sur les Moyennes Mobiles (MA50 et MA200).
 *
 * Signaux :
 * - BUY : Golden Cross (MA50 > MA200 et détection du croisement)
 * - SELL : Death Cross (MA50 < MA200 et détection du croisement)
 * - HOLD : Pas de croisement détecté
 */
final class MovingAverageStrategy implements TradingStrategyInterface
{
    /**
     * Analyse les indicateurs et retourne un signal de trading.
     */
    public function analyze(IndicatorsDTO $indicators, float $currentPrice): Signal
    {
        if ($indicators->ma50 === null || $indicators->ma200 === null) {
            Log::warning('MA strategy: MA50 or MA200 not available', [
                'ma50' => $indicators->ma50,
                'ma200' => $indicators->ma200,
                'current_price' => $currentPrice,
            ]);

            return Signal::Hold;
        }

        $ma50 = $indicators->ma50;
        $ma200 = $indicators->ma200;

        Log::info('MA strategy analysis', [
            'ma50' => $ma50,
            'ma200' => $ma200,
            'trend' => $indicators->trend,
            'golden_cross' => $indicators->goldenCross,
            'death_cross' => $indicators->deathCross,
            'current_price' => $currentPrice,
        ]);

        // Signal d'achat : Golden Cross détecté
        if ($indicators->goldenCross) {
            Log::info('MA strategy: BUY signal (Golden Cross)', [
                'ma50' => $ma50,
                'ma200' => $ma200,
            ]);

            return Signal::Buy;
        }

        // Signal de vente : Death Cross détecté
        if ($indicators->deathCross) {
            Log::info('MA strategy: SELL signal (Death Cross)', [
                'ma50' => $ma50,
                'ma200' => $ma200,
            ]);

            return Signal::Sell;
        }

        // Pas de croisement : pas de signal
        Log::debug('MA strategy: HOLD signal (no cross detected)', [
            'ma50' => $ma50,
            'ma200' => $ma200,
            'trend' => $indicators->trend,
        ]);

        return Signal::Hold;
    }

    /**
     * Retourne le nom de la stratégie.
     */
    public function getName(): string
    {
        return 'Moving Average Strategy';
    }

    /**
     * Retourne la description de la stratégie.
     */
    public function getDescription(): string
    {
        return 'Trading basé sur croisements MA50/MA200 : Achat au Golden Cross, Vente au Death Cross';
    }
}
