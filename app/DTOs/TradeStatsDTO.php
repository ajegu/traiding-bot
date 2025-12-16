<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class TradeStatsDTO
{
    public function __construct(
        public int $totalTrades,
        public int $buyCount,
        public int $sellCount,
        public int $winningTrades,
        public int $losingTrades,
        public float $winRate,
        public float $totalPnl,
        public float $totalPnlPercent,
        public float $averagePnl,
        public float $bestTrade,
        public float $worstTrade,
        public float $totalVolume,
        public float $totalFees,
    ) {}

    /**
     * Crée une instance avec les valeurs par défaut (aucun trade).
     */
    public static function empty(): self
    {
        return new self(
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            winningTrades: 0,
            losingTrades: 0,
            winRate: 0.0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            averagePnl: 0.0,
            bestTrade: 0.0,
            worstTrade: 0.0,
            totalVolume: 0.0,
            totalFees: 0.0,
        );
    }

    /**
     * Crée une instance depuis une collection de trades.
     *
     * @param  array<array<string, mixed>>  $trades
     */
    public static function fromTrades(array $trades, float $initialBalance = 0.0): self
    {
        if (empty($trades)) {
            return self::empty();
        }

        $buyCount = 0;
        $sellCount = 0;
        $winningTrades = 0;
        $losingTrades = 0;
        $totalPnl = 0.0;
        $totalVolume = 0.0;
        $totalFees = 0.0;
        $pnls = [];

        foreach ($trades as $trade) {
            $totalVolume += $trade['quote_quantity'] ?? 0;
            $totalFees += $trade['commission'] ?? 0;

            if (($trade['side'] ?? '') === 'BUY') {
                $buyCount++;
            } else {
                $sellCount++;
            }

            if (isset($trade['pnl'])) {
                $pnl = (float) $trade['pnl'];
                $totalPnl += $pnl;
                $pnls[] = $pnl;

                if ($pnl > 0) {
                    $winningTrades++;
                } elseif ($pnl < 0) {
                    $losingTrades++;
                }
            }
        }

        $totalTrades = count($trades);
        $tradesWithPnl = count($pnls);

        return new self(
            totalTrades: $totalTrades,
            buyCount: $buyCount,
            sellCount: $sellCount,
            winningTrades: $winningTrades,
            losingTrades: $losingTrades,
            winRate: $tradesWithPnl > 0 ? ($winningTrades / $tradesWithPnl) * 100 : 0.0,
            totalPnl: $totalPnl,
            totalPnlPercent: $initialBalance > 0 ? ($totalPnl / $initialBalance) * 100 : 0.0,
            averagePnl: $tradesWithPnl > 0 ? $totalPnl / $tradesWithPnl : 0.0,
            bestTrade: ! empty($pnls) ? max($pnls) : 0.0,
            worstTrade: ! empty($pnls) ? min($pnls) : 0.0,
            totalVolume: $totalVolume,
            totalFees: $totalFees,
        );
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'total_trades' => $this->totalTrades,
            'buy_count' => $this->buyCount,
            'sell_count' => $this->sellCount,
            'winning_trades' => $this->winningTrades,
            'losing_trades' => $this->losingTrades,
            'win_rate' => round($this->winRate, 2),
            'total_pnl' => round($this->totalPnl, 2),
            'total_pnl_percent' => round($this->totalPnlPercent, 2),
            'average_pnl' => round($this->averagePnl, 2),
            'best_trade' => round($this->bestTrade, 2),
            'worst_trade' => round($this->worstTrade, 2),
            'total_volume' => round($this->totalVolume, 2),
            'total_fees' => round($this->totalFees, 4),
        ];
    }
}
