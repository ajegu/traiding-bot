<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\OrderStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrderStatusTest extends TestCase
{
    #[Test]
    public function filled_status_is_final(): void
    {
        $this->assertTrue(OrderStatus::Filled->isFinal());
    }

    #[Test]
    public function new_status_is_not_final(): void
    {
        $this->assertFalse(OrderStatus::New->isFinal());
    }

    #[Test]
    public function partially_filled_is_not_final(): void
    {
        $this->assertFalse(OrderStatus::PartiallyFilled->isFinal());
    }

    #[Test]
    #[DataProvider('finalStatusesProvider')]
    public function final_statuses_are_correctly_identified(OrderStatus $status): void
    {
        $this->assertTrue($status->isFinal());
    }

    public static function finalStatusesProvider(): array
    {
        return [
            'filled' => [OrderStatus::Filled],
            'canceled' => [OrderStatus::Canceled],
            'rejected' => [OrderStatus::Rejected],
            'expired' => [OrderStatus::Expired],
            'error' => [OrderStatus::Error],
        ];
    }

    #[Test]
    public function filled_status_is_executed(): void
    {
        $this->assertTrue(OrderStatus::Filled->isExecuted());
    }

    #[Test]
    public function partially_filled_is_executed(): void
    {
        $this->assertTrue(OrderStatus::PartiallyFilled->isExecuted());
    }

    #[Test]
    public function new_status_is_not_executed(): void
    {
        $this->assertFalse(OrderStatus::New->isExecuted());
    }

    #[Test]
    public function new_status_is_pending(): void
    {
        $this->assertTrue(OrderStatus::New->isPending());
    }

    #[Test]
    public function filled_status_is_not_pending(): void
    {
        $this->assertFalse(OrderStatus::Filled->isPending());
    }

    #[Test]
    public function from_binance_converts_lowercase(): void
    {
        $status = OrderStatus::fromBinance('filled');
        $this->assertEquals(OrderStatus::Filled, $status);
    }

    #[Test]
    public function from_binance_handles_uppercase(): void
    {
        $status = OrderStatus::fromBinance('PARTIALLY_FILLED');
        $this->assertEquals(OrderStatus::PartiallyFilled, $status);
    }

    #[Test]
    public function all_statuses_have_colors(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertNotEmpty($status->color());
        }
    }

    #[Test]
    public function all_statuses_have_labels(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertNotEmpty($status->label());
        }
    }

    #[Test]
    public function filled_has_green_color(): void
    {
        $this->assertEquals('green', OrderStatus::Filled->color());
    }

    #[Test]
    public function error_has_red_color(): void
    {
        $this->assertEquals('red', OrderStatus::Error->color());
    }
}
