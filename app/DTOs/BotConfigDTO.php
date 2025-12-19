<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\Strategy;

final readonly class BotConfigDTO
{
    public function __construct(
        public bool $enabled,
        public string $symbol,
        public Strategy $strategy,
        public float $amount,
        public ?string $lastExecutionAt = null,
        public ?string $lastSignal = null,
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            symbol: $data['symbol'] ?? 'BTCUSDT',
            strategy: isset($data['strategy']) ? Strategy::from($data['strategy']) : Strategy::Rsi,
            amount: (float) ($data['amount'] ?? 100),
            lastExecutionAt: $data['last_execution_at'] ?? null,
            lastSignal: $data['last_signal'] ?? null,
        );
    }

    /**
     * Create default configuration.
     */
    public static function default(): self
    {
        return new self(
            enabled: false,
            symbol: config('bot.trading.symbol', 'BTCUSDT'),
            strategy: Strategy::from(config('bot.trading.strategy', 'rsi')),
            amount: (float) config('bot.trading.amount', 100),
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'symbol' => $this->symbol,
            'strategy' => $this->strategy->value,
            'amount' => $this->amount,
            'last_execution_at' => $this->lastExecutionAt,
            'last_signal' => $this->lastSignal,
        ];
    }
}
