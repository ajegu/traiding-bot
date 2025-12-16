<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use DateTimeImmutable;

final readonly class TradeResultDTO
{
    public function __construct(
        public string $orderId,
        public string $clientOrderId,
        public string $symbol,
        public OrderSide $side,
        public OrderType $type,
        public OrderStatus $status,
        public float $quantity,
        public float $price,
        public float $quoteQuantity,
        public float $commission,
        public string $commissionAsset,
        public DateTimeImmutable $executedAt,
    ) {}

    /**
     * Crée une instance depuis une réponse Binance.
     */
    public static function fromBinanceResponse(array $response): self
    {
        $fills = $response['fills'] ?? [];
        $commission = 0.0;
        $commissionAsset = 'USDT';

        foreach ($fills as $fill) {
            $commission += (float) ($fill['commission'] ?? 0);
            $commissionAsset = $fill['commissionAsset'] ?? 'USDT';
        }

        return new self(
            orderId: (string) $response['orderId'],
            clientOrderId: $response['clientOrderId'] ?? '',
            symbol: $response['symbol'],
            side: OrderSide::from($response['side']),
            type: OrderType::from($response['type']),
            status: OrderStatus::fromBinance($response['status']),
            quantity: (float) $response['executedQty'],
            price: self::calculateAveragePrice($response),
            quoteQuantity: (float) $response['cummulativeQuoteQty'],
            commission: $commission,
            commissionAsset: $commissionAsset,
            executedAt: new DateTimeImmutable,
        );
    }

    /**
     * Calcule le prix moyen d'exécution.
     */
    private static function calculateAveragePrice(array $response): float
    {
        $fills = $response['fills'] ?? [];

        if (empty($fills)) {
            return (float) ($response['price'] ?? 0);
        }

        $totalQty = 0.0;
        $totalValue = 0.0;

        foreach ($fills as $fill) {
            $qty = (float) $fill['qty'];
            $price = (float) $fill['price'];
            $totalQty += $qty;
            $totalValue += $qty * $price;
        }

        return $totalQty > 0 ? $totalValue / $totalQty : 0.0;
    }

    /**
     * Crée une instance depuis un tableau.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            orderId: $data['order_id'],
            clientOrderId: $data['client_order_id'] ?? '',
            symbol: $data['symbol'],
            side: OrderSide::from($data['side']),
            type: OrderType::from($data['type']),
            status: OrderStatus::fromBinance($data['status']),
            quantity: (float) $data['quantity'],
            price: (float) $data['price'],
            quoteQuantity: (float) $data['quote_quantity'],
            commission: (float) ($data['commission'] ?? 0),
            commissionAsset: $data['commission_asset'] ?? 'USDT',
            executedAt: new DateTimeImmutable($data['executed_at']),
        );
    }

    /**
     * Convertit en tableau pour stockage.
     */
    public function toArray(): array
    {
        return [
            'order_id' => $this->orderId,
            'client_order_id' => $this->clientOrderId,
            'symbol' => $this->symbol,
            'side' => $this->side->value,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'quote_quantity' => $this->quoteQuantity,
            'commission' => $this->commission,
            'commission_asset' => $this->commissionAsset,
            'executed_at' => $this->executedAt->format('c'),
        ];
    }

    /**
     * Calcule le montant total avec frais.
     */
    public function totalWithFees(): float
    {
        return $this->quoteQuantity + $this->commission;
    }
}
