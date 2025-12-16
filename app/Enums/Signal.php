<?php

declare(strict_types=1);

namespace App\Enums;

enum Signal: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';
    case Hold = 'HOLD';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
            self::Hold => 'Attente',
        };
    }

    /**
     * Retourne l'emoji correspondant.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::Buy => '🟢',
            self::Sell => '🔴',
            self::Hold => '⏸️',
        };
    }

    /**
     * Indique si le signal déclenche une action de trading.
     */
    public function isActionable(): bool
    {
        return $this !== self::Hold;
    }

    /**
     * Convertit le signal en OrderSide (si applicable).
     */
    public function toOrderSide(): ?OrderSide
    {
        return match ($this) {
            self::Buy => OrderSide::Buy,
            self::Sell => OrderSide::Sell,
            self::Hold => null,
        };
    }
}
