<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderType: string
{
    case Market = 'MARKET';
    case Limit = 'LIMIT';
    case StopLoss = 'STOP_LOSS';
    case StopLossLimit = 'STOP_LOSS_LIMIT';
    case TakeProfit = 'TAKE_PROFIT';
    case TakeProfitLimit = 'TAKE_PROFIT_LIMIT';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::Market => 'Market',
            self::Limit => 'Limit',
            self::StopLoss => 'Stop Loss',
            self::StopLossLimit => 'Stop Loss Limit',
            self::TakeProfit => 'Take Profit',
            self::TakeProfitLimit => 'Take Profit Limit',
        };
    }

    /**
     * Indique si l'ordre nécessite un prix.
     */
    public function requiresPrice(): bool
    {
        return match ($this) {
            self::Market => false,
            default => true,
        };
    }

    /**
     * Indique si c'est un ordre d'exécution immédiate.
     */
    public function isImmediate(): bool
    {
        return $this === self::Market;
    }
}
