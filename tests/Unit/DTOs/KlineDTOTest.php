<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\KlineDTO;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KlineDTOTest extends TestCase
{
    #[Test]
    public function from_binance_response_creates_dto(): void
    {
        $kline = [
            1701864000000,  // Open time (ms)
            '42000.00',     // Open
            '43000.00',     // High
            '41500.00',     // Low
            '42500.00',     // Close
            '100.5',        // Volume
            1701867600000,  // Close time (ms)
            '4250000.00',   // Quote volume
            1500,           // Number of trades
            '50.0',         // Taker buy base volume
            '2125000.00',   // Taker buy quote volume
            '0',            // Ignore
        ];

        $dto = KlineDTO::fromBinanceResponse($kline);

        $this->assertEquals(42000.0, $dto->open);
        $this->assertEquals(43000.0, $dto->high);
        $this->assertEquals(41500.0, $dto->low);
        $this->assertEquals(42500.0, $dto->close);
        $this->assertEquals(100.5, $dto->volume);
        $this->assertEquals(4250000.0, $dto->quoteVolume);
        $this->assertEquals(1500, $dto->numberOfTrades);
    }

    #[Test]
    public function from_array_creates_dto(): void
    {
        $data = [
            'open_time' => '2024-12-06T10:00:00+00:00',
            'open' => 42000.0,
            'high' => 43000.0,
            'low' => 41500.0,
            'close' => 42500.0,
            'volume' => 100.5,
            'close_time' => '2024-12-06T11:00:00+00:00',
            'quote_volume' => 4250000.0,
            'number_of_trades' => 1500,
        ];

        $dto = KlineDTO::fromArray($data);

        $this->assertEquals(42000.0, $dto->open);
        $this->assertEquals(43000.0, $dto->high);
        $this->assertEquals(42500.0, $dto->close);
    }

    #[Test]
    public function median_price_returns_average_of_high_and_low(): void
    {
        $dto = new KlineDTO(
            openTime: new DateTimeImmutable,
            open: 42000.0,
            high: 44000.0,
            low: 40000.0,
            close: 43000.0,
            volume: 100.0,
            closeTime: new DateTimeImmutable,
            quoteVolume: 4200000.0,
            numberOfTrades: 1000,
        );

        $this->assertEquals(42000.0, $dto->medianPrice());
    }

    #[Test]
    public function typical_price_returns_hlc_average(): void
    {
        $dto = new KlineDTO(
            openTime: new DateTimeImmutable,
            open: 42000.0,
            high: 45000.0,
            low: 40000.0,
            close: 43000.0,
            volume: 100.0,
            closeTime: new DateTimeImmutable,
            quoteVolume: 4200000.0,
            numberOfTrades: 1000,
        );

        // (45000 + 40000 + 43000) / 3 = 42666.666...
        $this->assertEqualsWithDelta(42666.67, $dto->typicalPrice(), 0.01);
    }

    #[Test]
    public function is_bullish_returns_true_when_close_greater_than_open(): void
    {
        $dto = new KlineDTO(
            openTime: new DateTimeImmutable,
            open: 42000.0,
            high: 44000.0,
            low: 41000.0,
            close: 43000.0,
            volume: 100.0,
            closeTime: new DateTimeImmutable,
            quoteVolume: 4200000.0,
            numberOfTrades: 1000,
        );

        $this->assertTrue($dto->isBullish());
        $this->assertFalse($dto->isBearish());
    }

    #[Test]
    public function is_bearish_returns_true_when_close_less_than_open(): void
    {
        $dto = new KlineDTO(
            openTime: new DateTimeImmutable,
            open: 43000.0,
            high: 44000.0,
            low: 41000.0,
            close: 42000.0,
            volume: 100.0,
            closeTime: new DateTimeImmutable,
            quoteVolume: 4200000.0,
            numberOfTrades: 1000,
        );

        $this->assertTrue($dto->isBearish());
        $this->assertFalse($dto->isBullish());
    }

    #[Test]
    public function change_percent_calculates_correctly(): void
    {
        $dto = new KlineDTO(
            openTime: new DateTimeImmutable,
            open: 40000.0,
            high: 44000.0,
            low: 39000.0,
            close: 42000.0,
            volume: 100.0,
            closeTime: new DateTimeImmutable,
            quoteVolume: 4200000.0,
            numberOfTrades: 1000,
        );

        // (42000 - 40000) / 40000 * 100 = 5%
        $this->assertEquals(5.0, $dto->changePercent());
    }

    #[Test]
    public function change_percent_returns_zero_when_open_is_zero(): void
    {
        $dto = new KlineDTO(
            openTime: new DateTimeImmutable,
            open: 0.0,
            high: 44000.0,
            low: 39000.0,
            close: 42000.0,
            volume: 100.0,
            closeTime: new DateTimeImmutable,
            quoteVolume: 4200000.0,
            numberOfTrades: 1000,
        );

        $this->assertEquals(0.0, $dto->changePercent());
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $openTime = new DateTimeImmutable('2024-12-06T10:00:00+00:00');
        $closeTime = new DateTimeImmutable('2024-12-06T11:00:00+00:00');

        $dto = new KlineDTO(
            openTime: $openTime,
            open: 42000.0,
            high: 43000.0,
            low: 41000.0,
            close: 42500.0,
            volume: 100.5,
            closeTime: $closeTime,
            quoteVolume: 4225000.0,
            numberOfTrades: 1500,
        );

        $array = $dto->toArray();

        $this->assertEquals(42000.0, $array['open']);
        $this->assertEquals(43000.0, $array['high']);
        $this->assertEquals(41000.0, $array['low']);
        $this->assertEquals(42500.0, $array['close']);
        $this->assertEquals(100.5, $array['volume']);
        $this->assertEquals(1500, $array['number_of_trades']);
    }
}
