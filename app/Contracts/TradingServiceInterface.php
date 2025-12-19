<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\TradingResultDTO;
use App\Enums\Strategy;

interface TradingServiceInterface
{
    /**
     * Execute the trading strategy.
     */
    public function executeStrategy(
        string $symbol,
        Strategy $strategy,
        float $amount,
        bool $dryRun = false,
    ): TradingResultDTO;
}
