<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\IndicatorsDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndicatorsDTOTest extends TestCase
{
    #[Test]
    public function is_rsi_oversold_returns_true_below_threshold(): void
    {
        $dto = new IndicatorsDTO(rsi: 25.0);

        $this->assertTrue($dto->isRsiOversold());
        $this->assertTrue($dto->isRsiOversold(30.0));
    }

    #[Test]
    public function is_rsi_oversold_returns_false_above_threshold(): void
    {
        $dto = new IndicatorsDTO(rsi: 35.0);

        $this->assertFalse($dto->isRsiOversold());
    }

    #[Test]
    public function is_rsi_oversold_returns_false_when_rsi_is_null(): void
    {
        $dto = new IndicatorsDTO;

        $this->assertFalse($dto->isRsiOversold());
    }

    #[Test]
    public function is_rsi_overbought_returns_true_above_threshold(): void
    {
        $dto = new IndicatorsDTO(rsi: 75.0);

        $this->assertTrue($dto->isRsiOverbought());
        $this->assertTrue($dto->isRsiOverbought(70.0));
    }

    #[Test]
    public function is_rsi_overbought_returns_false_below_threshold(): void
    {
        $dto = new IndicatorsDTO(rsi: 65.0);

        $this->assertFalse($dto->isRsiOverbought());
    }

    #[Test]
    public function is_golden_cross_returns_true_when_ma50_above_ma200(): void
    {
        $dto = new IndicatorsDTO(ma50: 42000.0, ma200: 40000.0);

        $this->assertTrue($dto->isGoldenCross());
        $this->assertFalse($dto->isDeathCross());
    }

    #[Test]
    public function is_death_cross_returns_true_when_ma50_below_ma200(): void
    {
        $dto = new IndicatorsDTO(ma50: 38000.0, ma200: 40000.0);

        $this->assertTrue($dto->isDeathCross());
        $this->assertFalse($dto->isGoldenCross());
    }

    #[Test]
    public function is_golden_cross_returns_false_when_ma_is_null(): void
    {
        $dto = new IndicatorsDTO(ma50: 42000.0);

        $this->assertFalse($dto->isGoldenCross());
        $this->assertFalse($dto->isDeathCross());
    }

    #[Test]
    public function is_price_above_ma50_returns_true(): void
    {
        $dto = new IndicatorsDTO(ma50: 40000.0, currentPrice: 42000.0);

        $this->assertTrue($dto->isPriceAboveMa50());
    }

    #[Test]
    public function is_price_above_ma50_returns_false_when_below(): void
    {
        $dto = new IndicatorsDTO(ma50: 42000.0, currentPrice: 40000.0);

        $this->assertFalse($dto->isPriceAboveMa50());
    }

    #[Test]
    public function is_price_above_ma200_returns_true(): void
    {
        $dto = new IndicatorsDTO(ma200: 38000.0, currentPrice: 42000.0);

        $this->assertTrue($dto->isPriceAboveMa200());
    }

    #[Test]
    public function with_rsi_creates_new_instance(): void
    {
        $dto = new IndicatorsDTO(ma50: 42000.0);
        $newDto = $dto->withRsi(45.0);

        $this->assertNull($dto->rsi);
        $this->assertEquals(45.0, $newDto->rsi);
        $this->assertEquals(42000.0, $newDto->ma50);
    }

    #[Test]
    public function with_moving_averages_creates_new_instance(): void
    {
        $dto = new IndicatorsDTO(rsi: 50.0);
        $newDto = $dto->withMovingAverages(42000.0, 40000.0);

        $this->assertNull($dto->ma50);
        $this->assertNull($dto->ma200);
        $this->assertEquals(42000.0, $newDto->ma50);
        $this->assertEquals(40000.0, $newDto->ma200);
        $this->assertEquals(50.0, $newDto->rsi);
    }

    #[Test]
    public function with_current_price_creates_new_instance(): void
    {
        $dto = new IndicatorsDTO(rsi: 50.0);
        $newDto = $dto->withCurrentPrice(42500.0);

        $this->assertNull($dto->currentPrice);
        $this->assertEquals(42500.0, $newDto->currentPrice);
    }

    #[Test]
    public function to_array_excludes_null_values(): void
    {
        $dto = new IndicatorsDTO(rsi: 45.5, ma50: 42000.0);

        $array = $dto->toArray();

        $this->assertArrayHasKey('rsi', $array);
        $this->assertArrayHasKey('ma50', $array);
        $this->assertArrayNotHasKey('ma200', $array);
        $this->assertArrayNotHasKey('current_price', $array);
        $this->assertEquals(45.5, $array['rsi']);
        $this->assertEquals(42000.0, $array['ma50']);
    }

    #[Test]
    public function to_array_rounds_values(): void
    {
        $dto = new IndicatorsDTO(rsi: 45.5678, ma50: 42000.1234);

        $array = $dto->toArray();

        $this->assertEquals(45.57, $array['rsi']);
        $this->assertEquals(42000.12, $array['ma50']);
    }
}
