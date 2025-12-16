<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Strategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StrategyTest extends TestCase
{
    #[Test]
    public function rsi_has_correct_value(): void
    {
        $this->assertEquals('rsi', Strategy::Rsi->value);
    }

    #[Test]
    public function moving_average_has_correct_value(): void
    {
        $this->assertEquals('ma', Strategy::MovingAverage->value);
    }

    #[Test]
    public function combined_has_correct_value(): void
    {
        $this->assertEquals('combined', Strategy::Combined->value);
    }

    #[Test]
    public function rsi_requires_only_rsi_indicator(): void
    {
        $indicators = Strategy::Rsi->requiredIndicators();

        $this->assertCount(1, $indicators);
        $this->assertContains('rsi', $indicators);
    }

    #[Test]
    public function moving_average_requires_ma50_and_ma200(): void
    {
        $indicators = Strategy::MovingAverage->requiredIndicators();

        $this->assertCount(2, $indicators);
        $this->assertContains('ma50', $indicators);
        $this->assertContains('ma200', $indicators);
    }

    #[Test]
    public function combined_requires_all_indicators(): void
    {
        $indicators = Strategy::Combined->requiredIndicators();

        $this->assertCount(3, $indicators);
        $this->assertContains('rsi', $indicators);
        $this->assertContains('ma50', $indicators);
        $this->assertContains('ma200', $indicators);
    }

    #[Test]
    public function all_strategies_have_display_names(): void
    {
        foreach (Strategy::cases() as $strategy) {
            $this->assertNotEmpty($strategy->displayName());
        }
    }

    #[Test]
    public function all_strategies_have_short_names(): void
    {
        foreach (Strategy::cases() as $strategy) {
            $this->assertNotEmpty($strategy->shortName());
        }
    }

    #[Test]
    public function all_strategies_have_descriptions(): void
    {
        foreach (Strategy::cases() as $strategy) {
            $this->assertNotEmpty($strategy->description());
        }
    }

    #[Test]
    public function rsi_short_name_is_rsi(): void
    {
        $this->assertEquals('RSI', Strategy::Rsi->shortName());
    }

    #[Test]
    public function moving_average_short_name_is_ma(): void
    {
        $this->assertEquals('MA', Strategy::MovingAverage->shortName());
    }
}
