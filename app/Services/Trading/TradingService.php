<?php

declare(strict_types=1);

namespace App\Services\Trading;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\TradingStrategyInterface;
use App\DTOs\TradingResultDTO;
use App\Enums\KlineInterval;
use App\Enums\Signal;
use App\Enums\Strategy;
use App\Exceptions\BinanceApiException;
use App\Exceptions\InsufficientBalanceException;
use App\Services\Trading\Indicators\IndicatorService;
use App\Services\Trading\Strategies\CombinedStrategy;
use App\Services\Trading\Strategies\MovingAverageStrategy;
use App\Services\Trading\Strategies\RsiStrategy;
use Illuminate\Support\Facades\Log;

/**
 * Service principal de trading qui orchestre l'analyse et l'exécution des trades.
 */
final class TradingService
{
    public function __construct(
        private readonly BinanceServiceInterface $binanceService,
        private readonly IndicatorService $indicatorService,
    ) {}

    /**
     * Exécute la stratégie de trading sur un symbole.
     *
     * @param  string  $symbol  Symbole à trader (ex: BTCUSDT)
     * @param  Strategy  $strategy  Stratégie à utiliser
     * @param  float  $amount  Montant à trader en USDT
     * @param  bool  $dryRun  Mode simulation (pas de trade réel)
     * @return TradingResultDTO Résultat de l'exécution
     */
    public function executeStrategy(
        string $symbol,
        Strategy $strategy,
        float $amount = 100.0,
        bool $dryRun = false
    ): TradingResultDTO {
        Log::info('Trading strategy execution started', [
            'symbol' => $symbol,
            'strategy' => $strategy->value,
            'amount' => $amount,
            'dry_run' => $dryRun,
        ]);

        try {
            // 1. Récupérer le prix actuel
            $currentPrice = $this->binanceService->getCurrentPrice($symbol);

            Log::info('Current price fetched', [
                'symbol' => $symbol,
                'price' => $currentPrice,
            ]);

            // 2. Récupérer les données historiques (klines)
            $klines = $this->binanceService->getKlines(
                symbol: $symbol,
                interval: KlineInterval::FiveMinutes,
                limit: 250 // Suffisant pour MA200 + buffer
            );

            Log::info('Klines fetched', [
                'symbol' => $symbol,
                'count' => count($klines),
            ]);

            // 3. Calculer les indicateurs techniques
            $indicators = $this->indicatorService->calculateFromKlines($klines);
            $indicators = $indicators->withCurrentPrice($currentPrice);

            Log::info('Indicators calculated', $indicators->toArray());

            // 4. Analyser la stratégie et obtenir un signal
            $strategyInstance = $this->getStrategyInstance($strategy);
            $signal = $strategyInstance->analyze($indicators, $currentPrice);

            Log::info('Strategy analysis completed', [
                'strategy' => $strategyInstance->getName(),
                'signal' => $signal->value,
            ]);

            // 5. Exécuter le trade si signal actionnable et pas en dry-run
            if ($signal->isActionable() && ! $dryRun) {
                return $this->executeTrade(
                    symbol: $symbol,
                    strategy: $strategy,
                    signal: $signal,
                    indicators: $indicators,
                    amount: $amount,
                    currentPrice: $currentPrice
                );
            }

            // Pas de trade : retourner le résultat sans trade
            $reason = $dryRun
                ? 'Dry-run mode: no real trade executed'
                : "Signal {$signal->value}: no action required";

            Log::info('No trade executed', [
                'signal' => $signal->value,
                'reason' => $reason,
            ]);

            return TradingResultDTO::noTrade(
                symbol: $symbol,
                strategy: $strategy,
                signal: $signal,
                indicators: $indicators,
                reason: $reason
            );
        } catch (BinanceApiException $e) {
            Log::error('Trading strategy execution failed: Binance API error', [
                'symbol' => $symbol,
                'strategy' => $strategy->value,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Trading strategy execution failed', [
                'symbol' => $symbol,
                'strategy' => $strategy->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Exécute le trade selon le signal.
     */
    private function executeTrade(
        string $symbol,
        Strategy $strategy,
        Signal $signal,
        $indicators,
        float $amount,
        float $currentPrice
    ): TradingResultDTO {
        try {
            $trade = match ($signal) {
                Signal::Buy => $this->binanceService->marketBuy($symbol, $amount),
                Signal::Sell => $this->executeSellTrade($symbol),
                Signal::Hold => throw new \LogicException('Cannot execute trade for HOLD signal'),
            };

            Log::info('Trade executed successfully', [
                'symbol' => $symbol,
                'signal' => $signal->value,
                'trade_id' => $trade->orderId,
            ]);

            return TradingResultDTO::withTrade(
                symbol: $symbol,
                strategy: $strategy,
                signal: $signal,
                indicators: $indicators,
                trade: $trade
            );
        } catch (InsufficientBalanceException $e) {
            Log::warning('Trade skipped: insufficient balance', [
                'symbol' => $symbol,
                'signal' => $signal->value,
                'error' => $e->getMessage(),
                'context' => $e->context(),
            ]);

            return TradingResultDTO::noTrade(
                symbol: $symbol,
                strategy: $strategy,
                signal: $signal,
                indicators: $indicators,
                reason: "Insufficient balance: {$e->getMessage()}"
            );
        }
    }

    /**
     * Exécute une vente en vendant toute la position disponible.
     */
    private function executeSellTrade(string $symbol): mixed
    {
        // Extraire l'actif de base (ex: BTC de BTCUSDT)
        $baseAsset = str_replace(['USDT', 'BUSD', 'USDC'], '', $symbol);

        // Récupérer le solde disponible
        $balance = $this->binanceService->getBalance($baseAsset);

        if ($balance === null || $balance->free <= 0) {
            throw new InsufficientBalanceException(
                message: "No {$baseAsset} available to sell",
                asset: $baseAsset,
                required: 0,
                available: $balance?->free ?? 0
            );
        }

        // Vendre tout le solde disponible
        return $this->binanceService->marketSell($symbol, $balance->free);
    }

    /**
     * Récupère l'instance de la stratégie correspondante.
     */
    private function getStrategyInstance(Strategy $strategy): TradingStrategyInterface
    {
        return match ($strategy) {
            Strategy::Rsi => new RsiStrategy(
                oversoldThreshold: config('bot.rsi.oversold', 30),
                overboughtThreshold: config('bot.rsi.overbought', 70)
            ),
            Strategy::MovingAverage => new MovingAverageStrategy(),
            Strategy::Combined => new CombinedStrategy(
                oversoldThreshold: config('bot.rsi.oversold', 30),
                overboughtThreshold: config('bot.rsi.overbought', 70)
            ),
        };
    }

    /**
     * Analyse uniquement sans exécuter de trade (toujours en dry-run).
     *
     * @param  string  $symbol  Symbole à analyser
     * @param  Strategy  $strategy  Stratégie à utiliser
     * @return TradingResultDTO Résultat de l'analyse
     */
    public function analyze(string $symbol, Strategy $strategy): TradingResultDTO
    {
        return $this->executeStrategy(
            symbol: $symbol,
            strategy: $strategy,
            dryRun: true
        );
    }

    /**
     * Vérifie si une position est ouverte pour un symbole.
     *
     * @param  string  $symbol  Symbole à vérifier
     * @return bool True si une position est ouverte
     */
    public function hasOpenPosition(string $symbol): bool
    {
        $baseAsset = str_replace(['USDT', 'BUSD', 'USDC'], '', $symbol);
        $balance = $this->binanceService->getBalance($baseAsset);

        return $balance !== null && $balance->free > 0;
    }
}
