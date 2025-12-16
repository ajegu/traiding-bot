<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\IndicatorsDTO;
use App\DTOs\TradeResultDTO;
use App\DTOs\TradingResultDTO;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\Signal;
use App\Enums\Strategy;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TradingResultDTOTest extends TestCase
{
    #[Test]
    public function has_traded_returns_false_when_no_trade(): void
    {
        $dto = new TradingResultDTO(
            symbol: 'BTCUSDT',
            strategy: Strategy::Rsi,
            signal: Signal::Hold,
            indicators: new IndicatorsDTO(rsi: 50.0),
            trade: null,
            reason: 'RSI in neutral zone',
        );

        $this->assertFalse($dto->hasTraded());
    }

    #[Test]
    public function has_traded_returns_true_when_trade_exists(): void
    {
        $trade = new TradeResultDTO(
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

        $dto = new TradingResultDTO(
            symbol: 'BTCUSDT',
            strategy: Strategy::Rsi,
            signal: Signal::Buy,
            indicators: new IndicatorsDTO(rsi: 25.0),
            trade: $trade,
        );

        $this->assertTrue($dto->hasTraded());
    }

    #[Test]
    public function no_trade_factory_creates_dto_without_trade(): void
    {
        $indicators = new IndicatorsDTO(rsi: 50.0);

        $dto = TradingResultDTO::noTrade(
            symbol: 'BTCUSDT',
            strategy: Strategy::Rsi,
            signal: Signal::Hold,
            indicators: $indicators,
            reason: 'RSI in neutral zone',
        );

        $this->assertEquals('BTCUSDT', $dto->symbol);
        $this->assertEquals(Strategy::Rsi, $dto->strategy);
        $this->assertEquals(Signal::Hold, $dto->signal);
        $this->assertNull($dto->trade);
        $this->assertEquals('RSI in neutral zone', $dto->reason);
    }

    #[Test]
    public function with_trade_factory_creates_dto_with_trade(): void
    {
        $indicators = new IndicatorsDTO(rsi: 25.0);
        $trade = new TradeResultDTO(
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

        $dto = TradingResultDTO::withTrade(
            symbol: 'BTCUSDT',
            strategy: Strategy::Rsi,
            signal: Signal::Buy,
            indicators: $indicators,
            trade: $trade,
        );

        $this->assertEquals('BTCUSDT', $dto->symbol);
        $this->assertEquals(Signal::Buy, $dto->signal);
        $this->assertNotNull($dto->trade);
        $this->assertNull($dto->reason);
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $dto = new TradingResultDTO(
            symbol: 'BTCUSDT',
            strategy: Strategy::MovingAverage,
            signal: Signal::Sell,
            indicators: new IndicatorsDTO(ma50: 42000.0, ma200: 40000.0),
            trade: null,
            reason: 'Insufficient balance',
        );

        $array = $dto->toArray();

        $this->assertEquals('BTCUSDT', $array['symbol']);
        $this->assertEquals('ma', $array['strategy']);
        $this->assertEquals('SELL', $array['signal']);
        $this->assertArrayHasKey('indicators', $array);
        $this->assertNull($array['trade']);
        $this->assertEquals('Insufficient balance', $array['reason']);
    }

    #[Test]
    public function to_array_includes_trade_when_present(): void
    {
        $trade = new TradeResultDTO(
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

        $dto = TradingResultDTO::withTrade(
            symbol: 'BTCUSDT',
            strategy: Strategy::Rsi,
            signal: Signal::Buy,
            indicators: new IndicatorsDTO(rsi: 25.0),
            trade: $trade,
        );

        $array = $dto->toArray();

        $this->assertIsArray($array['trade']);
        $this->assertEquals('123', $array['trade']['order_id']);
    }
}
