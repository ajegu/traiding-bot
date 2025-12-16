<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class IndicatorsDTO
{
    public function __construct(
        public ?float $rsi = null,
        public ?float $ma50 = null,
        public ?float $ma200 = null,
        public ?float $currentPrice = null,
    ) {}

    /**
     * Vérifie si le RSI indique une survente.
     */
    public function isRsiOversold(float $threshold = 30.0): bool
    {
        return $this->rsi !== null && $this->rsi < $threshold;
    }

    /**
     * Vérifie si le RSI indique un surachat.
     */
    public function isRsiOverbought(float $threshold = 70.0): bool
    {
        return $this->rsi !== null && $this->rsi > $threshold;
    }

    /**
     * Vérifie si on est en Golden Cross (MA50 > MA200).
     */
    public function isGoldenCross(): bool
    {
        return $this->ma50 !== null
            && $this->ma200 !== null
            && $this->ma50 > $this->ma200;
    }

    /**
     * Vérifie si on est en Death Cross (MA50 < MA200).
     */
    public function isDeathCross(): bool
    {
        return $this->ma50 !== null
            && $this->ma200 !== null
            && $this->ma50 < $this->ma200;
    }

    /**
     * Vérifie si le prix est au-dessus de la MA50.
     */
    public function isPriceAboveMa50(): bool
    {
        return $this->currentPrice !== null
            && $this->ma50 !== null
            && $this->currentPrice > $this->ma50;
    }

    /**
     * Vérifie si le prix est au-dessus de la MA200.
     */
    public function isPriceAboveMa200(): bool
    {
        return $this->currentPrice !== null
            && $this->ma200 !== null
            && $this->currentPrice > $this->ma200;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return array_filter([
            'rsi' => $this->rsi !== null ? round($this->rsi, 2) : null,
            'ma50' => $this->ma50 !== null ? round($this->ma50, 2) : null,
            'ma200' => $this->ma200 !== null ? round($this->ma200, 2) : null,
            'current_price' => $this->currentPrice,
        ], fn ($value) => $value !== null);
    }

    /**
     * Crée une instance avec un RSI.
     */
    public function withRsi(float $rsi): self
    {
        return new self(
            rsi: $rsi,
            ma50: $this->ma50,
            ma200: $this->ma200,
            currentPrice: $this->currentPrice,
        );
    }

    /**
     * Crée une instance avec des moyennes mobiles.
     */
    public function withMovingAverages(float $ma50, float $ma200): self
    {
        return new self(
            rsi: $this->rsi,
            ma50: $ma50,
            ma200: $ma200,
            currentPrice: $this->currentPrice,
        );
    }

    /**
     * Crée une instance avec le prix actuel.
     */
    public function withCurrentPrice(float $price): self
    {
        return new self(
            rsi: $this->rsi,
            ma50: $this->ma50,
            ma200: $this->ma200,
            currentPrice: $price,
        );
    }
}
