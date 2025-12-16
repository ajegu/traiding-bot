<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;

final readonly class DailyReportDTO
{
    /**
     * @param  array<TradeResultDTO>  $trades
     * @param  array<BalanceDTO>  $balances
     */
    public function __construct(
        public DateTimeImmutable $date,
        public TradeStatsDTO $stats,
        public array $trades,
        public array $balances,
        public float $totalBalanceUsdt,
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
        return $this->stats->totalPnl > 0;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'stats' => $this->stats->toArray(),
            'trades' => array_map(fn ($t) => $t->toArray(), $this->trades),
            'balances' => array_map(fn ($b) => $b->toArray(), $this->balances),
            'total_balance_usdt' => round($this->totalBalanceUsdt, 2),
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
