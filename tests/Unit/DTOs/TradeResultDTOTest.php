<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\TradeResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TradeResultDTOTest extends TestCase
{
    #[Test]
    public function from_binance_response_creates_dto(): void
    {
        $response = [
            'orderId' => 12345,
            'clientOrderId' => 'test123',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'status' => 'FILLED',
            'executedQty' => '0.001',
            'cummulativeQuoteQty' => '42.50',
            'fills' => [
                ['price' => '42500.00', 'qty' => '0.001', 'commission' => '0.0425', 'commissionAsset' => 'USDT'],
            ],
        ];

        $dto = TradeResultDTO::fromBinanceResponse($response);

        $this->assertEquals('12345', $dto->orderId);
        $this->assertEquals('test123', $dto->clientOrderId);
        $this->assertEquals('BTCUSDT', $dto->symbol);
        $this->assertEquals(OrderSide::Buy, $dto->side);
        $this->assertEquals(OrderType::Market, $dto->type);
        $this->assertEquals(OrderStatus::Filled, $dto->status);
        $this->assertEquals(0.001, $dto->quantity);
        $this->assertEquals(42500.0, $dto->price);
        $this->assertEquals(42.50, $dto->quoteQuantity);
        $this->assertEquals(0.0425, $dto->commission);
        $this->assertEquals('USDT', $dto->commissionAsset);
    }

    #[Test]
    public function from_binance_response_calculates_average_price_from_multiple_fills(): void
    {
        $response = [
            'orderId' => 12345,
            'clientOrderId' => 'test123',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'status' => 'FILLED',
            'executedQty' => '0.003',
            'cummulativeQuoteQty' => '127.50',
            'fills' => [
                ['price' => '42000.00', 'qty' => '0.001', 'commission' => '0.042', 'commissionAsset' => 'USDT'],
                ['price' => '42500.00', 'qty' => '0.001', 'commission' => '0.0425', 'commissionAsset' => 'USDT'],
                ['price' => '43000.00', 'qty' => '0.001', 'commission' => '0.043', 'commissionAsset' => 'USDT'],
            ],
        ];

        $dto = TradeResultDTO::fromBinanceResponse($response);

        // Average: (42000 + 42500 + 43000) / 3 = 42500
        $this->assertEquals(42500.0, $dto->price);
        // Total commission: 0.042 + 0.0425 + 0.043 = 0.1275
        $this->assertEquals(0.1275, $dto->commission);
    }

    #[Test]
    public function from_array_creates_dto(): void
    {
        $data = [
            'order_id' => '12345',
            'client_order_id' => 'test123',
            'symbol' => 'BTCUSDT',
            'side' => 'SELL',
            'type' => 'LIMIT',
            'status' => 'FILLED',
            'quantity' => 0.002,
            'price' => 43000.0,
            'quote_quantity' => 86.0,
            'commission' => 0.086,
            'commission_asset' => 'USDT',
            'executed_at' => '2024-12-06T10:30:00+00:00',
        ];

        $dto = TradeResultDTO::fromArray($data);

        $this->assertEquals('12345', $dto->orderId);
        $this->assertEquals(OrderSide::Sell, $dto->side);
        $this->assertEquals(OrderType::Limit, $dto->type);
        $this->assertEquals(0.002, $dto->quantity);
        $this->assertEquals(43000.0, $dto->price);
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $executedAt = new DateTimeImmutable('2024-12-06T10:30:00+00:00');
        $dto = new TradeResultDTO(
            orderId: '123',
            clientOrderId: 'test',
            symbol: 'BTCUSDT',
            side: OrderSide::Buy,
            type: OrderType::Market,
            status: OrderStatus::Filled,
            quantity: 0.001,
            price: 42500.0,
            quoteQuantity: 42.50,
            commission: 0.05,
            commissionAsset: 'USDT',
            executedAt: $executedAt,
        );

        $array = $dto->toArray();

        $this->assertEquals('123', $array['order_id']);
        $this->assertEquals('test', $array['client_order_id']);
        $this->assertEquals('BTCUSDT', $array['symbol']);
        $this->assertEquals('BUY', $array['side']);
        $this->assertEquals('MARKET', $array['type']);
        $this->assertEquals('FILLED', $array['status']);
        $this->assertEquals(0.001, $array['quantity']);
        $this->assertEquals(42500.0, $array['price']);
        $this->assertEquals(42.50, $array['quote_quantity']);
        $this->assertEquals(0.05, $array['commission']);
        $this->assertEquals('USDT', $array['commission_asset']);
    }

    #[Test]
    public function total_with_fees_returns_correct_value(): void
    {
        $dto = new TradeResultDTO(
            orderId: '123',
            clientOrderId: 'test',
            symbol: 'BTCUSDT',
            side: OrderSide::Buy,
            type: OrderType::Market,
            status: OrderStatus::Filled,
            quantity: 0.001,
            price: 42500.0,
            quoteQuantity: 42.50,
            commission: 0.05,
            commissionAsset: 'USDT',
            executedAt: new DateTimeImmutable,
        );

        $this->assertEquals(42.55, $dto->totalWithFees());
    }

    #[Test]
    public function from_binance_response_handles_empty_fills(): void
    {
        $response = [
            'orderId' => 12345,
            'clientOrderId' => 'test123',
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'type' => 'MARKET',
            'status' => 'FILLED',
            'executedQty' => '0.001',
            'cummulativeQuoteQty' => '42.50',
            'price' => '42500.00',
            'fills' => [],
        ];

        $dto = TradeResultDTO::fromBinanceResponse($response);

        $this->assertEquals(42500.0, $dto->price);
        $this->assertEquals(0.0, $dto->commission);
    }
}
