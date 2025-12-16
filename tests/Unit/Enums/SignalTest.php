<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderSide;
use App\Enums\Signal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SignalTest extends TestCase
{
    #[Test]
    public function buy_signal_has_correct_value(): void
    {
        $this->assertEquals('BUY', Signal::Buy->value);
    }

    #[Test]
    public function sell_signal_has_correct_value(): void
    {
        $this->assertEquals('SELL', Signal::Sell->value);
    }

    #[Test]
    public function hold_signal_has_correct_value(): void
    {
        $this->assertEquals('HOLD', Signal::Hold->value);
    }

    #[Test]
    public function buy_signal_is_actionable(): void
    {
        $this->assertTrue(Signal::Buy->isActionable());
    }

    #[Test]
    public function sell_signal_is_actionable(): void
    {
        $this->assertTrue(Signal::Sell->isActionable());
    }

    #[Test]
    public function hold_signal_is_not_actionable(): void
    {
        $this->assertFalse(Signal::Hold->isActionable());
    }

    #[Test]
    public function buy_signal_converts_to_buy_order_side(): void
    {
        $this->assertEquals(OrderSide::Buy, Signal::Buy->toOrderSide());
    }

    #[Test]
    public function sell_signal_converts_to_sell_order_side(): void
    {
        $this->assertEquals(OrderSide::Sell, Signal::Sell->toOrderSide());
    }

    #[Test]
    public function hold_signal_returns_null_order_side(): void
    {
        $this->assertNull(Signal::Hold->toOrderSide());
    }

    #[Test]
    public function all_signals_have_labels(): void
    {
        foreach (Signal::cases() as $signal) {
            $this->assertNotEmpty($signal->label());
        }
    }

    #[Test]
    public function all_signals_have_emojis(): void
    {
        foreach (Signal::cases() as $signal) {
            $this->assertNotEmpty($signal->emoji());
        }
    }

    #[Test]
    public function buy_has_green_emoji(): void
    {
        $this->assertEquals('🟢', Signal::Buy->emoji());
    }

    #[Test]
    public function sell_has_red_emoji(): void
    {
        $this->assertEquals('🔴', Signal::Sell->emoji());
    }

    #[Test]
    public function hold_has_pause_emoji(): void
    {
        $this->assertEquals('⏸️', Signal::Hold->emoji());
    }
}
