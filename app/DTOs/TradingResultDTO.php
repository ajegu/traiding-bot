<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Signal;
use App\Enums\Strategy;

final readonly class TradingResultDTO
{
    public function __construct(
        public string $symbol,
        public Strategy $strategy,
        public Signal $signal,
        public IndicatorsDTO $indicators,
        public ?TradeResultDTO $trade = null,
        public ?string $reason = null,
    ) {}

    /**
     * Vérifie si un trade a été exécuté.
     */
    public function hasTraded(): bool
    {
        return $this->trade !== null;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'strategy' => $this->strategy->value,
            'signal' => $this->signal->value,
            'indicators' => $this->indicators->toArray(),
            'trade' => $this->trade?->toArray(),
            'reason' => $this->reason,
        ];
    }

    /**
     * Crée un résultat sans trade.
     */
    public static function noTrade(
        string $symbol,
        Strategy $strategy,
        Signal $signal,
        IndicatorsDTO $indicators,
        string $reason,
    ): self {
        return new self(
            symbol: $symbol,
            strategy: $strategy,
            signal: $signal,
            indicators: $indicators,
            trade: null,
            reason: $reason,
        );
    }

    /**
     * Crée un résultat avec trade.
     */
    public static function withTrade(
        string $symbol,
        Strategy $strategy,
        Signal $signal,
        IndicatorsDTO $indicators,
        TradeResultDTO $trade,
    ): self {
        return new self(
            symbol: $symbol,
            strategy: $strategy,
            signal: $signal,
            indicators: $indicators,
            trade: $trade,
            reason: null,
        );
    }
}
