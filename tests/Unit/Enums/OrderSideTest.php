<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderSide;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderSideTest extends TestCase
{
    #[Test]
    public function buy_has_correct_value(): void
    {
        $this->assertEquals('BUY', OrderSide::Buy->value);
    }

    #[Test]
    public function sell_has_correct_value(): void
    {
        $this->assertEquals('SELL', OrderSide::Sell->value);
    }

    #[Test]
    public function buy_has_correct_label(): void
    {
        $this->assertEquals('Achat', OrderSide::Buy->label());
    }

    #[Test]
    public function sell_has_correct_label(): void
    {
        $this->assertEquals('Vente', OrderSide::Sell->label());
    }

    #[Test]
    public function buy_has_green_emoji(): void
    {
        $this->assertEquals('🟢', OrderSide::Buy->emoji());
    }

    #[Test]
    public function sell_has_red_emoji(): void
    {
        $this->assertEquals('🔴', OrderSide::Sell->emoji());
    }

    #[Test]
    public function buy_is_opposite_to_sell(): void
    {
        $this->assertTrue(OrderSide::Buy->isOpposite(OrderSide::Sell));
    }

    #[Test]
    public function buy_is_not_opposite_to_buy(): void
    {
        $this->assertFalse(OrderSide::Buy->isOpposite(OrderSide::Buy));
    }

    #[Test]
    public function buy_opposite_is_sell(): void
    {
        $this->assertEquals(OrderSide::Sell, OrderSide::Buy->opposite());
    }

    #[Test]
    public function sell_opposite_is_buy(): void
    {
        $this->assertEquals(OrderSide::Buy, OrderSide::Sell->opposite());
    }
}
