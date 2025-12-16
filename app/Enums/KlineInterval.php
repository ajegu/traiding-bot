<?php

declare(strict_types=1);

namespace App\Enums;

enum KlineInterval: string
{
    case OneMinute = '1m';
    case ThreeMinutes = '3m';
    case FiveMinutes = '5m';
    case FifteenMinutes = '15m';
    case ThirtyMinutes = '30m';
    case OneHour = '1h';
    case TwoHours = '2h';
    case FourHours = '4h';
    case SixHours = '6h';
    case EightHours = '8h';
    case TwelveHours = '12h';
    case OneDay = '1d';
    case ThreeDays = '3d';
    case OneWeek = '1w';
    case OneMonth = '1M';

    /**
     * Retourne la durée en secondes.
     */
    public function toSeconds(): int
    {
        return match ($this) {
            self::OneMinute => 60,
            self::ThreeMinutes => 180,
            self::FiveMinutes => 300,
            self::FifteenMinutes => 900,
            self::ThirtyMinutes => 1800,
            self::OneHour => 3600,
            self::TwoHours => 7200,
            self::FourHours => 14400,
            self::SixHours => 21600,
            self::EightHours => 28800,
            self::TwelveHours => 43200,
            self::OneDay => 86400,
            self::ThreeDays => 259200,
            self::OneWeek => 604800,
            self::OneMonth => 2592000,
        };
    }

    /**
     * Retourne le libellé lisible.
     */
    public function label(): string
    {
        return match ($this) {
            self::OneMinute => '1 minute',
            self::ThreeMinutes => '3 minutes',
            self::FiveMinutes => '5 minutes',
            self::FifteenMinutes => '15 minutes',
            self::ThirtyMinutes => '30 minutes',
            self::OneHour => '1 heure',
            self::TwoHours => '2 heures',
            self::FourHours => '4 heures',
            self::SixHours => '6 heures',
            self::EightHours => '8 heures',
            self::TwelveHours => '12 heures',
            self::OneDay => '1 jour',
            self::ThreeDays => '3 jours',
            self::OneWeek => '1 semaine',
            self::OneMonth => '1 mois',
        };
    }

    /**
     * Retourne l'intervalle par défaut pour le bot.
     */
    public static function default(): self
    {
        return self::FiveMinutes;
    }
}
