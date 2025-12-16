<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;

final class BotConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $strategy = 'rsi',
        public string $symbol = 'BTCUSDT',
        public float $amount = 100.0,
        public ?string $lastExecution = null,
        public ?string $lastSignal = null,
        public ?Carbon $updatedAt = null,
    ) {
        if ($this->updatedAt === null) {
            $this->updatedAt = Carbon::now();
        }
    }

    /**
     * Crée une instance depuis un tableau de données.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: $data['enabled'] ?? false,
            strategy: $data['strategy'] ?? 'rsi',
            symbol: $data['symbol'] ?? 'BTCUSDT',
            amount: isset($data['amount']) ? (float) $data['amount'] : 100.0,
            lastExecution: $data['last_execution'] ?? null,
            lastSignal: $data['last_signal'] ?? null,
            updatedAt: isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : null,
        );
    }

    /**
     * Crée une instance depuis les données DynamoDB.
     */
    public static function fromDynamoDB(array $item): self
    {
        return new self(
            enabled: isset($item['enabled']['BOOL']) ? $item['enabled']['BOOL'] : false,
            strategy: $item['strategy']['S'] ?? 'rsi',
            symbol: $item['symbol']['S'] ?? 'BTCUSDT',
            amount: isset($item['amount']['N']) ? (float) $item['amount']['N'] : 100.0,
            lastExecution: $item['last_execution']['S'] ?? null,
            lastSignal: $item['last_signal']['S'] ?? null,
            updatedAt: isset($item['updated_at']['S']) ? Carbon::parse($item['updated_at']['S']) : null,
        );
    }

    /**
     * Convertit en tableau pour DynamoDB.
     */
    public function toDynamoDB(): array
    {
        $item = [
            'pk' => ['S' => 'CONFIG#bot'],
            'sk' => ['S' => 'SETTINGS'],
            'enabled' => ['BOOL' => $this->enabled],
            'strategy' => ['S' => $this->strategy],
            'symbol' => ['S' => $this->symbol],
            'amount' => ['N' => (string) $this->amount],
            'updated_at' => ['S' => $this->updatedAt->toIso8601String()],
        ];

        // Champs optionnels
        if ($this->lastExecution !== null) {
            $item['last_execution'] = ['S' => $this->lastExecution];
        }
        if ($this->lastSignal !== null) {
            $item['last_signal'] = ['S' => $this->lastSignal];
        }

        return $item;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'strategy' => $this->strategy,
            'symbol' => $this->symbol,
            'amount' => $this->amount,
            'last_execution' => $this->lastExecution,
            'last_signal' => $this->lastSignal,
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }

    /**
     * Crée une configuration par défaut.
     */
    public static function default(): self
    {
        return new self(
            enabled: false,
            strategy: config('bot.trading.strategy', 'rsi'),
            symbol: config('bot.trading.symbol', 'BTCUSDT'),
            amount: config('bot.trading.amount', 100.0),
        );
    }
}
