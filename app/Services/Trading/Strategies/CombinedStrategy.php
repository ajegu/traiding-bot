<?php

declare(strict_types=1);

namespace App\Services\Trading\Strategies;

use App\Contracts\TradingStrategyInterface;
use App\DTOs\IndicatorsDTO;
use App\Enums\Signal;
use Illuminate\Support\Facades\Log;

/**
 * Stratégie de trading combinée (RSI + Moyennes Mobiles).
 *
 * Signaux :
 * - BUY : RSI < 30 ET tendance haussière (MA50 > MA200)
 * - SELL : RSI > 70 ET tendance baissière (MA50 < MA200)
 * - HOLD : Conditions non remplies
 *
 * Cette stratégie combine deux indicateurs pour des signaux plus fiables.
 */
final class CombinedStrategy implements TradingStrategyInterface
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
            Log::warning('Combined strategy: RSI not available', [
                'current_price' => $currentPrice,
            ]);

            return Signal::Hold;
        }

        if ($indicators->ma50 === null || $indicators->ma200 === null) {
            Log::warning('Combined strategy: MA50 or MA200 not available', [
                'ma50' => $indicators->ma50,
                'ma200' => $indicators->ma200,
            ]);

            return Signal::Hold;
        }

        $rsi = $indicators->rsi;
        $ma50 = $indicators->ma50;
        $ma200 = $indicators->ma200;
        $trend = $indicators->trend;

        Log::info('Combined strategy analysis', [
            'rsi' => $rsi,
            'ma50' => $ma50,
            'ma200' => $ma200,
            'trend' => $trend,
            'current_price' => $currentPrice,
        ]);

        // Signal d'achat : RSI en survente ET tendance haussière
        if ($rsi < $this->oversoldThreshold && $trend === 'bullish') {
            Log::info('Combined strategy: BUY signal', [
                'rsi' => $rsi,
                'oversold_threshold' => $this->oversoldThreshold,
                'trend' => $trend,
                'ma50' => $ma50,
                'ma200' => $ma200,
            ]);

            return Signal::Buy;
        }

        // Signal de vente : RSI en surachat ET tendance baissière
        if ($rsi > $this->overboughtThreshold && $trend === 'bearish') {
            Log::info('Combined strategy: SELL signal', [
                'rsi' => $rsi,
                'overbought_threshold' => $this->overboughtThreshold,
                'trend' => $trend,
                'ma50' => $ma50,
                'ma200' => $ma200,
            ]);

            return Signal::Sell;
        }

        // Conditions non remplies : pas de signal
        Log::debug('Combined strategy: HOLD signal', [
            'rsi' => $rsi,
            'trend' => $trend,
            'reason' => $this->getHoldReason($rsi, $trend),
        ]);

        return Signal::Hold;
    }

    /**
     * Retourne le nom de la stratégie.
     */
    public function getName(): string
    {
        return 'Combined Strategy (RSI + MA)';
    }

    /**
     * Retourne la description de la stratégie.
     */
    public function getDescription(): string
    {
        return sprintf(
            'Trading basé sur RSI et MA : Achat si RSI < %.1f ET MA50 > MA200, Vente si RSI > %.1f ET MA50 < MA200',
            $this->oversoldThreshold,
            $this->overboughtThreshold
        );
    }

    /**
     * Retourne la raison du signal HOLD.
     */
    private function getHoldReason(float $rsi, ?string $trend): string
    {
        if ($rsi < $this->oversoldThreshold && $trend !== 'bullish') {
            return "RSI oversold but trend not bullish (trend: {$trend})";
        }

        if ($rsi > $this->overboughtThreshold && $trend !== 'bearish') {
            return "RSI overbought but trend not bearish (trend: {$trend})";
        }

        return 'RSI in neutral zone';
    }
}
