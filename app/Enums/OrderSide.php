<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderSide: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Achat',
            self::Sell => 'Vente',
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
        };
    }

    /**
     * Vérifie si c'est le côté opposé.
     */
    public function isOpposite(self $other): bool
    {
        return $this !== $other;
    }

    /**
     * Retourne le côté opposé.
     */
    public function opposite(): self
    {
        return match ($this) {
            self::Buy => self::Sell,
            self::Sell => self::Buy,
        };
    }
}
