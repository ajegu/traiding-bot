<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\ReportRepositoryInterface;
use App\Contracts\ReportServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\DTOs\DailyReportDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Report;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Service de génération de rapports et calcul de P&L.
 */
final class ReportService implements ReportServiceInterface
{
    /**
     * Commission Binance par défaut (0.1%).
     */
    private const DEFAULT_COMMISSION_RATE = 0.001;

    public function __construct(
        private readonly TradeRepositoryInterface $tradeRepository,
        private readonly BinanceServiceInterface $binanceService,
        private readonly ReportRepositoryInterface $reportRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function generateDailyReport(?Carbon $date = null): DailyReportDTO
    {
        $date = $date ?? Carbon::yesterday();

        Log::info('Generating daily report', [
            'date' => $date->format('Y-m-d'),
        ]);

        // 1. Récupérer tous les trades du jour
        $trades = $this->tradeRepository->findByDate($date);

        // 2. Calculer le P&L de la journée
        $pnlData = $this->calculatePnl($date->startOfDay(), $date->endOfDay());

        // 3. Récupérer les soldes du compte
        $portfolioData = $this->getPortfolioValue();

        // 4. Compter les trades par type
        $buyCount = $trades->filter(fn (Trade $t) => $t->side === OrderSide::Buy)->count();
        $sellCount = $trades->filter(fn (Trade $t) => $t->side === OrderSide::Sell)->count();

        // 5. Créer le DTO du rapport
        $report = new DailyReportDTO(
            date: $date,
            totalTrades: $trades->count(),
            buyCount: $buyCount,
            sellCount: $sellCount,
            totalPnl: $pnlData['pnl'],
            totalPnlPercent: $pnlData['pnl_percent'],
            balances: $portfolioData['balances'],
            totalBalanceUsdt: $portfolioData['total_usdt'],
            trades: $trades->all(),
        );

        Log::info('Daily report generated', [
            'date' => $date->format('Y-m-d'),
            'total_trades' => $report->totalTrades,
            'pnl' => $report->totalPnl,
        ]);

        return $report;
    }

    /**
     * {@inheritDoc}
     */
    public function calculatePnl(Carbon $from, Carbon $to): array
    {
        Log::debug('Calculating P&L', [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        $trades = $this->tradeRepository->findByDateRange($from, $to);

        $totalPnl = 0.0;
        $winningTrades = 0;
        $losingTrades = 0;
        $totalInvested = 0.0;

        // Séparer les achats et les ventes
        $buys = $trades->filter(fn (Trade $t) => $t->side === OrderSide::Buy && $t->status === OrderStatus::Filled);
        $sells = $trades->filter(fn (Trade $t) => $t->side === OrderSide::Sell && $t->status === OrderStatus::Filled);

        // Calculer le P&L pour chaque vente
        foreach ($sells as $sell) {
            if ($sell->pnl !== null) {
                // P&L déjà calculé lors de la vente
                $totalPnl += $sell->pnl;

                if ($sell->pnl > 0) {
                    $winningTrades++;
                } elseif ($sell->pnl < 0) {
                    $losingTrades++;
                }
            } else {
                // Calculer le P&L si non disponible
                $pnl = $this->calculateTradePnl($sell->id);
                if ($pnl !== null) {
                    $totalPnl += $pnl;

                    if ($pnl > 0) {
                        $winningTrades++;
                    } elseif ($pnl < 0) {
                        $losingTrades++;
                    }
                }
            }
        }

        // Calculer le montant total investi (pour le P&L%)
        foreach ($buys as $buy) {
            $totalInvested += $buy->quoteQuantity;
        }

        // Calculer le P&L en pourcentage
        $pnlPercent = $totalInvested > 0 ? ($totalPnl / $totalInvested) * 100 : 0.0;

        return [
            'pnl' => $totalPnl,
            'pnl_percent' => $pnlPercent,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function calculateTradePnl(string $tradeId): ?float
    {
        $sellTrade = $this->tradeRepository->findById($tradeId);

        if ($sellTrade === null || $sellTrade->side !== OrderSide::Sell) {
            Log::warning('Trade not found or not a sell order', ['trade_id' => $tradeId]);

            return null;
        }

        // Si le trade a déjà un trade d'achat lié
        if ($sellTrade->relatedTradeId !== null) {
            $buyTrade = $this->tradeRepository->findById($sellTrade->relatedTradeId);
        } else {
            // Chercher le trade d'achat correspondant (FIFO)
            $buyTrade = $this->findMatchingBuyTrade($sellTrade);
        }

        if ($buyTrade === null) {
            Log::warning('No matching buy trade found', [
                'sell_trade_id' => $tradeId,
                'symbol' => $sellTrade->symbol,
            ]);

            return null;
        }

        // Calculer le P&L
        // P&L = (prix_vente - prix_achat) * quantité - frais_total
        $sellRevenue = $sellTrade->quantity * $sellTrade->price;
        $buyCost = $buyTrade->quantity * $buyTrade->price;

        $sellFees = $sellTrade->commission ?? ($sellRevenue * self::DEFAULT_COMMISSION_RATE);
        $buyFees = $buyTrade->commission ?? ($buyCost * self::DEFAULT_COMMISSION_RATE);

        $pnl = $sellRevenue - $buyCost - $sellFees - $buyFees;

        Log::debug('P&L calculated for trade', [
            'sell_trade_id' => $sellTrade->id,
            'buy_trade_id' => $buyTrade->id,
            'pnl' => $pnl,
            'sell_revenue' => $sellRevenue,
            'buy_cost' => $buyCost,
            'total_fees' => $sellFees + $buyFees,
        ]);

        return $pnl;
    }

    /**
     * {@inheritDoc}
     */
    public function getPortfolioValue(): array
    {
        Log::debug('Fetching portfolio value');

        // Récupérer tous les soldes
        $balances = $this->binanceService->getAccountBalances();

        $portfolioBalances = [];
        $totalUsdt = 0.0;

        // Assets principaux à convertir en USDT (limiter les appels API)
        // Sur testnet, il y a des dizaines d'assets de test, on se limite aux principaux
        $mainAssets = ['BTC', 'ETH', 'BNB', 'USDT', 'USDC', 'BUSD', 'XRP', 'SOL', 'ADA', 'DOGE'];

        // Compteur d'assets convertis (limiter pour éviter les timeouts)
        $convertedCount = 0;
        $maxConversions = 10;

        foreach ($balances as $balance) {
            $asset = $balance->asset;
            $free = $balance->free;
            $locked = $balance->locked;
            $total = $free + $locked;

            // Ignorer les soldes nuls
            if ($total <= 0) {
                continue;
            }

            $portfolioBalances[$asset] = $total;

            // Calculer la valeur en USDT
            if (in_array($asset, ['USDT', 'USDC', 'BUSD', 'TUSD'])) {
                // Stablecoins : valeur 1:1
                $totalUsdt += $total;
            } elseif (in_array($asset, $mainAssets) && $convertedCount < $maxConversions) {
                // Assets principaux : récupérer le prix
                try {
                    $symbol = $asset.'USDT';
                    $price = $this->binanceService->getCurrentPrice($symbol);
                    $valueUsdt = $total * $price;
                    $totalUsdt += $valueUsdt;
                    $convertedCount++;

                    Log::debug('Asset value calculated', [
                        'asset' => $asset,
                        'quantity' => $total,
                        'price_usdt' => $price,
                        'value_usdt' => $valueUsdt,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Failed to get price for asset', [
                        'asset' => $asset,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            // Les autres assets sont listés mais pas convertis
        }

        Log::info('Portfolio value calculated', [
            'total_usdt' => $totalUsdt,
            'assets_count' => count($portfolioBalances),
            'assets_converted' => $convertedCount,
        ]);

        return [
            'total_usdt' => $totalUsdt,
            'balances' => $portfolioBalances,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function archiveReport(DailyReportDTO $report): bool
    {
        Log::info('Archiving daily report', [
            'date' => $report->date->format('Y-m-d'),
        ]);

        $reportModel = new Report(
            date: $report->date,
            tradesCount: $report->totalTrades,
            pnlAbsolute: $report->totalPnl,
            pnlPercent: $report->totalPnlPercent,
            totalBalanceUsdt: $report->totalBalanceUsdt,
            messageId: null, // Sera mis à jour après l'envoi Telegram
            createdAt: Carbon::now(),
        );

        try {
            $this->reportRepository->create($reportModel);

            Log::info('Daily report archived', [
                'date' => $report->date->format('Y-m-d'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to archive daily report', [
                'date' => $report->date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Trouve le trade d'achat correspondant (FIFO - First In First Out).
     *
     * Recherche le plus ancien trade d'achat non associé pour le même symbole.
     */
    private function findMatchingBuyTrade(Trade $sellTrade): ?Trade
    {
        // Récupérer toutes les positions ouvertes pour ce symbole
        $openPositions = $this->tradeRepository->getOpenPositions($sellTrade->symbol);

        // Trier par date (FIFO)
        $sortedPositions = $openPositions->sortBy('createdAt');

        // Chercher le premier achat avec une quantité >= à la vente
        foreach ($sortedPositions as $position) {
            if ($position->quantity >= $sellTrade->quantity) {
                return $position;
            }
        }

        // Si aucune position exacte, prendre la première position ouverte
        return $sortedPositions->first();
    }
}
