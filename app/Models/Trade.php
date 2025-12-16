<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Carbon\Carbon;
use Illuminate\Support\Str;

final class Trade
{
    public function __construct(
        public ?string $id = null,
        public ?int $orderId = null,
        public ?string $clientOrderId = null,
        public ?string $symbol = null,
        public ?OrderSide $side = null,
        public ?OrderType $type = null,
        public ?OrderStatus $status = null,
        public ?float $quantity = null,
        public ?float $price = null,
        public ?float $quoteQuantity = null,
        public ?float $commission = null,
        public ?string $commissionAsset = null,
        public ?string $strategy = null,
        public ?float $signalValue = null,
        public ?array $indicators = null,
        public ?float $pnl = null,
        public ?float $pnlPercent = null,
        public ?string $relatedTradeId = null,
        public ?string $notes = null,
        public ?Carbon $createdAt = null,
        public ?Carbon $updatedAt = null,
    ) {
        if ($this->id === null) {
            $this->id = (string) Str::uuid();
        }
        if ($this->createdAt === null) {
            $this->createdAt = Carbon::now();
        }
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
            id: $data['id'] ?? null,
            orderId: isset($data['order_id']) ? (int) $data['order_id'] : null,
            clientOrderId: $data['client_order_id'] ?? null,
            symbol: $data['symbol'] ?? null,
            side: isset($data['side']) ? OrderSide::from($data['side']) : null,
            type: isset($data['type']) ? OrderType::from($data['type']) : null,
            status: isset($data['status']) ? OrderStatus::from($data['status']) : null,
            quantity: isset($data['quantity']) ? (float) $data['quantity'] : null,
            price: isset($data['price']) ? (float) $data['price'] : null,
            quoteQuantity: isset($data['quote_quantity']) ? (float) $data['quote_quantity'] : null,
            commission: isset($data['commission']) ? (float) $data['commission'] : null,
            commissionAsset: $data['commission_asset'] ?? null,
            strategy: $data['strategy'] ?? null,
            signalValue: isset($data['signal_value']) ? (float) $data['signal_value'] : null,
            indicators: $data['indicators'] ?? null,
            pnl: isset($data['pnl']) ? (float) $data['pnl'] : null,
            pnlPercent: isset($data['pnl_percent']) ? (float) $data['pnl_percent'] : null,
            relatedTradeId: $data['related_trade_id'] ?? null,
            notes: $data['notes'] ?? null,
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : null,
        );
    }

    /**
     * Crée une instance depuis les données DynamoDB.
     */
    public static function fromDynamoDB(array $item): self
    {
        return new self(
            id: $item['id']['S'] ?? null,
            orderId: isset($item['order_id']['N']) ? (int) $item['order_id']['N'] : null,
            clientOrderId: $item['client_order_id']['S'] ?? null,
            symbol: $item['symbol']['S'] ?? null,
            side: isset($item['side']['S']) ? OrderSide::from($item['side']['S']) : null,
            type: isset($item['type']['S']) ? OrderType::from($item['type']['S']) : null,
            status: isset($item['status']['S']) ? OrderStatus::from($item['status']['S']) : null,
            quantity: isset($item['quantity']['N']) ? (float) $item['quantity']['N'] : null,
            price: isset($item['price']['N']) ? (float) $item['price']['N'] : null,
            quoteQuantity: isset($item['quote_quantity']['N']) ? (float) $item['quote_quantity']['N'] : null,
            commission: isset($item['commission']['N']) ? (float) $item['commission']['N'] : null,
            commissionAsset: $item['commission_asset']['S'] ?? null,
            strategy: $item['strategy']['S'] ?? null,
            signalValue: isset($item['signal_value']['N']) ? (float) $item['signal_value']['N'] : null,
            indicators: isset($item['indicators']['M']) ? self::parseIndicators($item['indicators']['M']) : null,
            pnl: isset($item['pnl']['N']) ? (float) $item['pnl']['N'] : null,
            pnlPercent: isset($item['pnl_percent']['N']) ? (float) $item['pnl_percent']['N'] : null,
            relatedTradeId: $item['related_trade_id']['S'] ?? null,
            notes: $item['notes']['S'] ?? null,
            createdAt: isset($item['created_at']['S']) ? Carbon::parse($item['created_at']['S']) : null,
            updatedAt: isset($item['updated_at']['S']) ? Carbon::parse($item['updated_at']['S']) : null,
        );
    }

    /**
     * Parse les indicateurs depuis le format DynamoDB.
     */
    private static function parseIndicators(array $dynamoMap): array
    {
        $indicators = [];
        foreach ($dynamoMap as $key => $value) {
            if (isset($value['N'])) {
                $indicators[$key] = (float) $value['N'];
            } elseif (isset($value['S'])) {
                $indicators[$key] = $value['S'];
            }
        }

        return $indicators;
    }

    /**
     * Convertit en tableau pour DynamoDB.
     */
    public function toDynamoDB(): array
    {
        $item = [
            'pk' => ['S' => "TRADE#{$this->id}"],
            'sk' => ['S' => 'METADATA'],
            'id' => ['S' => $this->id],
            'symbol' => ['S' => $this->symbol],
            'side' => ['S' => $this->side->value],
            'type' => ['S' => $this->type->value],
            'status' => ['S' => $this->status->value],
            'quantity' => ['N' => (string) $this->quantity],
            'price' => ['N' => (string) $this->price],
            'quote_quantity' => ['N' => (string) $this->quoteQuantity],
            'commission' => ['N' => (string) $this->commission],
            'commission_asset' => ['S' => $this->commissionAsset],
            'strategy' => ['S' => $this->strategy],
            'created_at' => ['S' => $this->createdAt->toIso8601String()],
            'updated_at' => ['S' => $this->updatedAt->toIso8601String()],
            // GSI keys
            'gsi1pk' => ['S' => "SYMBOL#{$this->symbol}"],
            'gsi1sk' => ['S' => $this->createdAt->toIso8601String()],
            'gsi2pk' => ['S' => 'DATE#'.$this->createdAt->format('Y-m-d')],
            'gsi2sk' => ['S' => $this->createdAt->toIso8601String()],
            'gsi3pk' => ['S' => "STATUS#{$this->status->value}"],
            'gsi3sk' => ['S' => $this->createdAt->toIso8601String()],
        ];

        // Champs optionnels
        if ($this->orderId !== null) {
            $item['order_id'] = ['N' => (string) $this->orderId];
        }
        if ($this->clientOrderId !== null) {
            $item['client_order_id'] = ['S' => $this->clientOrderId];
        }
        if ($this->signalValue !== null) {
            $item['signal_value'] = ['N' => (string) $this->signalValue];
        }
        if ($this->indicators !== null && ! empty($this->indicators)) {
            $item['indicators'] = ['M' => $this->indicatorsToDynamoDB()];
        }
        if ($this->pnl !== null) {
            $item['pnl'] = ['N' => (string) $this->pnl];
        }
        if ($this->pnlPercent !== null) {
            $item['pnl_percent'] = ['N' => (string) $this->pnlPercent];
        }
        if ($this->relatedTradeId !== null) {
            $item['related_trade_id'] = ['S' => $this->relatedTradeId];
        }
        if ($this->notes !== null) {
            $item['notes'] = ['S' => $this->notes];
        }

        return $item;
    }

    /**
     * Convertit les indicateurs au format DynamoDB.
     */
    private function indicatorsToDynamoDB(): array
    {
        $dynamoMap = [];
        foreach ($this->indicators as $key => $value) {
            if (is_numeric($value)) {
                $dynamoMap[$key] = ['N' => (string) $value];
            } elseif (is_string($value)) {
                $dynamoMap[$key] = ['S' => $value];
            }
        }

        return $dynamoMap;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'client_order_id' => $this->clientOrderId,
            'symbol' => $this->symbol,
            'side' => $this->side?->value,
            'type' => $this->type?->value,
            'status' => $this->status?->value,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'quote_quantity' => $this->quoteQuantity,
            'commission' => $this->commission,
            'commission_asset' => $this->commissionAsset,
            'strategy' => $this->strategy,
            'signal_value' => $this->signalValue,
            'indicators' => $this->indicators,
            'pnl' => $this->pnl,
            'pnl_percent' => $this->pnlPercent,
            'related_trade_id' => $this->relatedTradeId,
            'notes' => $this->notes,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
        ];
    }
}
