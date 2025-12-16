<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\IndicatorsDTO;
use App\Enums\Signal;

interface TradingStrategyInterface
{
    /**
     * Analyse les indicateurs et retourne un signal de trading.
     *
     * @param  IndicatorsDTO  $indicators  Indicateurs techniques calculés
     * @param  float  $currentPrice  Prix actuel
     * @return Signal Signal de trading (BUY, SELL, HOLD)
     */
    public function analyze(IndicatorsDTO $indicators, float $currentPrice): Signal;

    /**
     * Retourne le nom de la stratégie.
     */
    public function getName(): string;

    /**
     * Retourne la description de la stratégie.
     */
    public function getDescription(): string;
}
