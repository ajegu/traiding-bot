<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\BalanceDTO;
use App\DTOs\DailyReportDTO;
use App\DTOs\TradeStatsDTO;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DailyReportDTOTest extends TestCase
{
    #[Test]
    public function daily_change_percent_calculates_correctly(): void
    {
        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: [],
            totalBalanceUsdt: 11000.0,
            previousDayBalanceUsdt: 10000.0,
        );

        $this->assertEquals(10.0, $dto->dailyChangePercent());
    }

    #[Test]
    public function daily_change_percent_returns_null_without_previous_balance(): void
    {
        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: [],
            totalBalanceUsdt: 11000.0,
            previousDayBalanceUsdt: null,
        );

        $this->assertNull($dto->dailyChangePercent());
    }

    #[Test]
    public function daily_change_percent_returns_null_when_previous_is_zero(): void
    {
        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: [],
            totalBalanceUsdt: 11000.0,
            previousDayBalanceUsdt: 0.0,
        );

        $this->assertNull($dto->dailyChangePercent());
    }

    #[Test]
    public function daily_change_absolute_calculates_correctly(): void
    {
        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: [],
            totalBalanceUsdt: 11000.0,
            previousDayBalanceUsdt: 10000.0,
        );

        $this->assertEquals(1000.0, $dto->dailyChangeAbsolute());
    }

    #[Test]
    public function daily_change_absolute_can_be_negative(): void
    {
        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: [],
            totalBalanceUsdt: 9000.0,
            previousDayBalanceUsdt: 10000.0,
        );

        $this->assertEquals(-1000.0, $dto->dailyChangeAbsolute());
    }

    #[Test]
    public function daily_change_absolute_returns_null_without_previous(): void
    {
        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: [],
            totalBalanceUsdt: 11000.0,
            previousDayBalanceUsdt: null,
        );

        $this->assertNull($dto->dailyChangeAbsolute());
    }

    #[Test]
    public function is_positive_day_returns_true_when_pnl_positive(): void
    {
        $stats = new TradeStatsDTO(
            totalTrades: 5,
            buyCount: 3,
            sellCount: 2,
            winningTrades: 2,
            losingTrades: 0,
            winRate: 100.0,
            totalPnl: 500.0,
            totalPnlPercent: 5.0,
            averagePnl: 250.0,
            bestTrade: 300.0,
            worstTrade: 200.0,
            totalVolume: 10000.0,
            totalFees: 10.0,
        );

        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: $stats,
            trades: [],
            balances: [],
            totalBalanceUsdt: 10500.0,
        );

        $this->assertTrue($dto->isPositiveDay());
    }

    #[Test]
    public function is_positive_day_returns_false_when_pnl_negative(): void
    {
        $stats = new TradeStatsDTO(
            totalTrades: 5,
            buyCount: 3,
            sellCount: 2,
            winningTrades: 0,
            losingTrades: 2,
            winRate: 0.0,
            totalPnl: -200.0,
            totalPnlPercent: -2.0,
            averagePnl: -100.0,
            bestTrade: -50.0,
            worstTrade: -150.0,
            totalVolume: 10000.0,
            totalFees: 10.0,
        );

        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: $stats,
            trades: [],
            balances: [],
            totalBalanceUsdt: 9800.0,
        );

        $this->assertFalse($dto->isPositiveDay());
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $balances = [
            new BalanceDTO('BTC', 0.5, 0.0),
            new BalanceDTO('USDT', 5000.0, 100.0),
        ];

        $dto = new DailyReportDTO(
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: $balances,
            totalBalanceUsdt: 26000.0,
            previousDayBalanceUsdt: 25000.0,
        );

        $array = $dto->toArray();

        $this->assertEquals('2024-12-06', $array['date']);
        $this->assertIsArray($array['stats']);
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
            date: new DateTimeImmutable('2024-12-06'),
            stats: TradeStatsDTO::empty(),
            trades: [],
            balances: [],
            totalBalanceUsdt: 10000.0,
            previousDayBalanceUsdt: null,
        );

        $array = $dto->toArray();

        $this->assertNull($array['previous_day_balance_usdt']);
        $this->assertNull($array['daily_change_percent']);
        $this->assertNull($array['daily_change_absolute']);
    }
}
