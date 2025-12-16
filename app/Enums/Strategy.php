<?php

declare(strict_types=1);

namespace App\Enums;

enum Strategy: string
{
    case Rsi = 'rsi';
    case MovingAverage = 'ma';
    case Combined = 'combined';

    /**
     * Retourne le nom complet de la stratégie.
     */
    public function displayName(): string
    {
        return match ($this) {
            self::Rsi => 'RSI (Relative Strength Index)',
            self::MovingAverage => 'Moyennes Mobiles (MA50/MA200)',
            self::Combined => 'RSI + Moyennes Mobiles',
        };
    }

    /**
     * Retourne le nom court.
     */
    public function shortName(): string
    {
        return match ($this) {
            self::Rsi => 'RSI',
            self::MovingAverage => 'MA',
            self::Combined => 'RSI+MA',
        };
    }

    /**
     * Retourne la description de la stratégie.
     */
    public function description(): string
    {
        return match ($this) {
            self::Rsi => 'Achète quand RSI < 30 (survente), vend quand RSI > 70 (surachat)',
            self::MovingAverage => 'Achète au Golden Cross (MA50 > MA200), vend au Death Cross',
            self::Combined => 'Combine les signaux RSI et MA pour plus de confirmation',
        };
    }

    /**
     * Retourne les indicateurs requis pour cette stratégie.
     *
     * @return array<string>
     */
    public function requiredIndicators(): array
    {
        return match ($this) {
            self::Rsi => ['rsi'],
            self::MovingAverage => ['ma50', 'ma200'],
            self::Combined => ['rsi', 'ma50', 'ma200'],
        };
    }
}
