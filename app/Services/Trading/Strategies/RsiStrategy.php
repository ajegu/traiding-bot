<?php

declare(strict_types=1);

namespace App\Services\Trading\Strategies;

use App\Contracts\TradingStrategyInterface;
use App\DTOs\IndicatorsDTO;
use App\Enums\Signal;
use Illuminate\Support\Facades\Log;

/**
 * Stratégie de trading basée sur le RSI (Relative Strength Index).
 *
 * Signaux :
 * - BUY : RSI < 30 (survente)
 * - SELL : RSI > 70 (surachat)
 * - HOLD : 30 <= RSI <= 70 (neutral)
 */
final class RsiStrategy implements TradingStrategyInterface
{
    public function __construct(
        private readonly float $oversoldThreshold = 30.0,
        private readonly float $overboughtThreshold = 70.0,
    ) {}

    /**
     * Analyse les indicateurs et retourne un signal de trading.
     */
    public function analyze(IndicatorsDTO $indicators, float $currentPrice): Signal
    {
        if ($indicators->rsi === null) {
            Log::warning('RSI strategy: RSI not available', [
                'current_price' => $currentPrice,
            ]);

            return Signal::Hold;
        }

        $rsi = $indicators->rsi;

        Log::info('RSI strategy analysis', [
            'rsi' => $rsi,
            'oversold_threshold' => $this->oversoldThreshold,
            'overbought_threshold' => $this->overboughtThreshold,
            'current_price' => $currentPrice,
        ]);

        // Signal d'achat : RSI en survente
        if ($rsi < $this->oversoldThreshold) {
            Log::info('RSI strategy: BUY signal (oversold)', [
                'rsi' => $rsi,
                'threshold' => $this->oversoldThreshold,
            ]);

            return Signal::Buy;
        }

        // Signal de vente : RSI en surachat
        if ($rsi > $this->overboughtThreshold) {
            Log::info('RSI strategy: SELL signal (overbought)', [
                'rsi' => $rsi,
                'threshold' => $this->overboughtThreshold,
            ]);

            return Signal::Sell;
        }

        // Zone neutre : pas de signal
        Log::debug('RSI strategy: HOLD signal (neutral)', [
            'rsi' => $rsi,
        ]);

        return Signal::Hold;
    }

    /**
     * Retourne le nom de la stratégie.
     */
    public function getName(): string
    {
        return 'RSI Strategy';
    }

    /**
     * Retourne la description de la stratégie.
     */
    public function getDescription(): string
    {
        return sprintf(
            'Trading basé sur RSI : Achat si RSI < %.1f (survente), Vente si RSI > %.1f (surachat)',
            $this->oversoldThreshold,
            $this->overboughtThreshold
        );
    }
}
