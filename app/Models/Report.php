<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;

final class Report
{
    public function __construct(
        public Carbon $date,
        public int $tradesCount = 0,
        public float $pnlAbsolute = 0.0,
        public float $pnlPercent = 0.0,
        public float $totalBalanceUsdt = 0.0,
        public ?int $messageId = null,
        public ?Carbon $createdAt = null,
        public ?int $ttl = null,
    ) {
        if ($this->createdAt === null) {
            $this->createdAt = Carbon::now();
        }
    }

    /**
     * Crée une instance depuis un tableau de données.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            date: Carbon::parse($data['date']),
            tradesCount: $data['trades_count'] ?? 0,
            pnlAbsolute: isset($data['pnl_absolute']) ? (float) $data['pnl_absolute'] : 0.0,
            pnlPercent: isset($data['pnl_percent']) ? (float) $data['pnl_percent'] : 0.0,
            totalBalanceUsdt: isset($data['total_balance_usdt']) ? (float) $data['total_balance_usdt'] : 0.0,
            messageId: isset($data['message_id']) ? (int) $data['message_id'] : null,
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null,
            ttl: isset($data['ttl']) ? (int) $data['ttl'] : null,
        );
    }

    /**
     * Crée une instance depuis les données DynamoDB.
     */
    public static function fromDynamoDB(array $item): self
    {
        return new self(
            date: Carbon::parse($item['date']['S']),
            tradesCount: isset($item['trades_count']['N']) ? (int) $item['trades_count']['N'] : 0,
            pnlAbsolute: isset($item['pnl_absolute']['N']) ? (float) $item['pnl_absolute']['N'] : 0.0,
            pnlPercent: isset($item['pnl_percent']['N']) ? (float) $item['pnl_percent']['N'] : 0.0,
            totalBalanceUsdt: isset($item['total_balance_usdt']['N']) ? (float) $item['total_balance_usdt']['N'] : 0.0,
            messageId: isset($item['message_id']['N']) ? (int) $item['message_id']['N'] : null,
            createdAt: isset($item['created_at']['S']) ? Carbon::parse($item['created_at']['S']) : null,
            ttl: isset($item['ttl']['N']) ? (int) $item['ttl']['N'] : null,
        );
    }

    /**
     * Convertit en tableau pour DynamoDB.
     */
    public function toDynamoDB(): array
    {
        $item = [
            'pk' => ['S' => 'REPORT#'.$this->date->format('Y-m-d')],
            'sk' => ['S' => 'DAILY'],
            'date' => ['S' => $this->date->format('Y-m-d')],
            'trades_count' => ['N' => (string) $this->tradesCount],
            'pnl_absolute' => ['N' => (string) $this->pnlAbsolute],
            'pnl_percent' => ['N' => (string) $this->pnlPercent],
            'total_balance_usdt' => ['N' => (string) $this->totalBalanceUsdt],
            'created_at' => ['S' => $this->createdAt->toIso8601String()],
        ];

        // Champs optionnels
        if ($this->messageId !== null) {
            $item['message_id'] = ['N' => (string) $this->messageId];
        }
        if ($this->ttl !== null) {
            $item['ttl'] = ['N' => (string) $this->ttl];
        }

        return $item;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'trades_count' => $this->tradesCount,
            'pnl_absolute' => $this->pnlAbsolute,
            'pnl_percent' => $this->pnlPercent,
            'total_balance_usdt' => $this->totalBalanceUsdt,
            'message_id' => $this->messageId,
            'created_at' => $this->createdAt?->toIso8601String(),
            'ttl' => $this->ttl,
        ];
    }

    /**
     * Définit un TTL (Time To Live) pour ce rapport.
     * Utile pour archiver automatiquement après X jours.
     */
    public function setTtl(int $daysFromNow): void
    {
        $this->ttl = Carbon::now()->addDays($daysFromNow)->timestamp;
    }
}
