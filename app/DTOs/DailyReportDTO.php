<?php

declare(strict_types=1);

namespace App\DTOs;

use Carbon\Carbon;

final readonly class DailyReportDTO
{
    /**
     * @param  array<TradeResultDTO>  $trades
     * @param  array<string, float>  $balances  Balances par asset (ex: ['BTC' => 0.5, 'USDT' => 1000])
     */
    public function __construct(
        public Carbon $date,
        public int $totalTrades,
        public int $buyCount,
        public int $sellCount,
        public float $totalPnl,
        public float $totalPnlPercent,
        public array $balances,
        public float $totalBalanceUsdt,
        public array $trades = [],
        public ?float $previousDayBalanceUsdt = null,
    ) {}

    /**
     * Calcule la variation journalière en pourcentage.
     */
    public function dailyChangePercent(): ?float
    {
        if ($this->previousDayBalanceUsdt === null || $this->previousDayBalanceUsdt <= 0) {
            return null;
        }

        return (($this->totalBalanceUsdt - $this->previousDayBalanceUsdt)
            / $this->previousDayBalanceUsdt) * 100;
    }

    /**
     * Calcule la variation journalière absolue.
     */
    public function dailyChangeAbsolute(): ?float
    {
        if ($this->previousDayBalanceUsdt === null) {
            return null;
        }

        return $this->totalBalanceUsdt - $this->previousDayBalanceUsdt;
    }

    /**
     * Vérifie si la journée a été positive.
     */
    public function isPositiveDay(): bool
    {
        return $this->totalPnl > 0;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'total_trades' => $this->totalTrades,
            'buy_count' => $this->buyCount,
            'sell_count' => $this->sellCount,
            'total_pnl' => round($this->totalPnl, 2),
            'total_pnl_percent' => round($this->totalPnlPercent, 2),
            'balances' => $this->balances,
            'total_balance_usdt' => round($this->totalBalanceUsdt, 2),
            'trades' => array_map(fn ($t) => $t->toArray(), $this->trades),
            'previous_day_balance_usdt' => $this->previousDayBalanceUsdt !== null
                ? round($this->previousDayBalanceUsdt, 2)
                : null,
            'daily_change_percent' => $this->dailyChangePercent() !== null
                ? round($this->dailyChangePercent(), 2)
                : null,
            'daily_change_absolute' => $this->dailyChangeAbsolute() !== null
                ? round($this->dailyChangeAbsolute(), 2)
                : null,
        ];
    }
}
