<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderTypeTest extends TestCase
{
    #[Test]
    public function market_has_correct_value(): void
    {
        $this->assertEquals('MARKET', OrderType::Market->value);
    }

    #[Test]
    public function limit_has_correct_value(): void
    {
        $this->assertEquals('LIMIT', OrderType::Limit->value);
    }

    #[Test]
    public function market_does_not_require_price(): void
    {
        $this->assertFalse(OrderType::Market->requiresPrice());
    }

    #[Test]
    public function limit_requires_price(): void
    {
        $this->assertTrue(OrderType::Limit->requiresPrice());
    }

    #[Test]
    public function stop_loss_requires_price(): void
    {
        $this->assertTrue(OrderType::StopLoss->requiresPrice());
    }

    #[Test]
    public function market_is_immediate(): void
    {
        $this->assertTrue(OrderType::Market->isImmediate());
    }

    #[Test]
    public function limit_is_not_immediate(): void
    {
        $this->assertFalse(OrderType::Limit->isImmediate());
    }

    #[Test]
    public function all_types_have_labels(): void
    {
        foreach (OrderType::cases() as $type) {
            $this->assertNotEmpty($type->label());
        }
    }
}
