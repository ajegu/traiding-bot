<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class BalanceDTO
{
    public function __construct(
        public string $asset,
        public float $free,
        public float $locked,
    ) {}

    /**
     * Crée une instance depuis une réponse Binance.
     */
    public static function fromBinanceResponse(array $data): self
    {
        return new self(
            asset: $data['asset'],
            free: (float) $data['free'],
            locked: (float) $data['locked'],
        );
    }

    /**
     * Crée une instance depuis un tableau.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            asset: $data['asset'],
            free: (float) $data['free'],
            locked: (float) ($data['locked'] ?? 0),
        );
    }

    /**
     * Retourne le solde total (libre + bloqué).
     */
    public function total(): float
    {
        return $this->free + $this->locked;
    }

    /**
     * Vérifie si le solde est vide.
     */
    public function isEmpty(): bool
    {
        return $this->total() <= 0;
    }

    /**
     * Vérifie si le solde est significatif (> seuil minimum).
     */
    public function isSignificant(float $minAmount = 0.00001): bool
    {
        return $this->total() > $minAmount;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'asset' => $this->asset,
            'free' => $this->free,
            'locked' => $this->locked,
            'total' => $this->total(),
        ];
    }
}
