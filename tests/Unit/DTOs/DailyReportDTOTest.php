<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\DailyReportDTO;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DailyReportDTOTest extends TestCase
{
    #[Test]
    public function daily_change_percent_calculates_correctly(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [],
            totalBalanceUsdt: 11000.0,
            trades: [],
            previousDayBalanceUsdt: 10000.0,
        );

        $this->assertEquals(10.0, $dto->dailyChangePercent());
    }

    #[Test]
    public function daily_change_percent_returns_null_without_previous_balance(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [],
            totalBalanceUsdt: 11000.0,
            trades: [],
            previousDayBalanceUsdt: null,
        );

        $this->assertNull($dto->dailyChangePercent());
    }

    #[Test]
    public function daily_change_percent_returns_null_when_previous_is_zero(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [],
            totalBalanceUsdt: 11000.0,
            trades: [],
            previousDayBalanceUsdt: 0.0,
        );

        $this->assertNull($dto->dailyChangePercent());
    }

    #[Test]
    public function daily_change_absolute_calculates_correctly(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [],
            totalBalanceUsdt: 11000.0,
            trades: [],
            previousDayBalanceUsdt: 10000.0,
        );

        $this->assertEquals(1000.0, $dto->dailyChangeAbsolute());
    }

    #[Test]
    public function daily_change_absolute_can_be_negative(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [],
            totalBalanceUsdt: 9000.0,
            trades: [],
            previousDayBalanceUsdt: 10000.0,
        );

        $this->assertEquals(-1000.0, $dto->dailyChangeAbsolute());
    }

    #[Test]
    public function daily_change_absolute_returns_null_without_previous(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [],
            totalBalanceUsdt: 11000.0,
            trades: [],
            previousDayBalanceUsdt: null,
        );

        $this->assertNull($dto->dailyChangeAbsolute());
    }

    #[Test]
    public function is_positive_day_returns_true_when_pnl_positive(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 5,
            buyCount: 3,
            sellCount: 2,
            totalPnl: 500.0,
            totalPnlPercent: 5.0,
            balances: [],
            totalBalanceUsdt: 10500.0,
        );

        $this->assertTrue($dto->isPositiveDay());
    }

    #[Test]
    public function is_positive_day_returns_false_when_pnl_negative(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 5,
            buyCount: 3,
            sellCount: 2,
            totalPnl: -200.0,
            totalPnlPercent: -2.0,
            balances: [],
            totalBalanceUsdt: 9800.0,
        );

        $this->assertFalse($dto->isPositiveDay());
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $balances = [
            'BTC' => 0.5,
            'USDT' => 5000.0,
        ];

        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 3,
            buyCount: 2,
            sellCount: 1,
            totalPnl: 100.0,
            totalPnlPercent: 0.4,
            balances: $balances,
            totalBalanceUsdt: 26000.0,
            trades: [],
            previousDayBalanceUsdt: 25000.0,
        );

        $array = $dto->toArray();

        $this->assertEquals('2024-12-06', $array['date']);
        $this->assertEquals(3, $array['total_trades']);
        $this->assertEquals(2, $array['buy_count']);
        $this->assertEquals(1, $array['sell_count']);
        $this->assertEquals(100.0, $array['total_pnl']);
        $this->assertEquals(0.4, $array['total_pnl_percent']);
        $this->assertIsArray($array['trades']);
        $this->assertCount(2, $array['balances']);
        $this->assertEquals(26000.0, $array['total_balance_usdt']);
        $this->assertEquals(25000.0, $array['previous_day_balance_usdt']);
        $this->assertEquals(4.0, $array['daily_change_percent']);
        $this->assertEquals(1000.0, $array['daily_change_absolute']);
    }

    #[Test]
    public function to_array_handles_null_previous_balance(): void
    {
        $dto = new DailyReportDTO(
            date: Carbon::parse('2024-12-06'),
            totalTrades: 0,
            buyCount: 0,
            sellCount: 0,
            totalPnl: 0.0,
            totalPnlPercent: 0.0,
            balances: [],
            totalBalanceUsdt: 10000.0,
            trades: [],
            previousDayBalanceUsdt: null,
        );

        $array = $dto->toArray();

        $this->assertNull($array['previous_day_balance_usdt']);
        $this->assertNull($array['daily_change_percent']);
        $this->assertNull($array['daily_change_absolute']);
    }
}
