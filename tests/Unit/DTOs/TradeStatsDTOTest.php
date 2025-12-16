<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\TradeStatsDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TradeStatsDTOTest extends TestCase
{
    #[Test]
    public function empty_returns_zeroed_stats(): void
    {
        $stats = TradeStatsDTO::empty();

        $this->assertEquals(0, $stats->totalTrades);
        $this->assertEquals(0, $stats->buyCount);
        $this->assertEquals(0, $stats->sellCount);
        $this->assertEquals(0, $stats->winningTrades);
        $this->assertEquals(0, $stats->losingTrades);
        $this->assertEquals(0.0, $stats->winRate);
        $this->assertEquals(0.0, $stats->totalPnl);
        $this->assertEquals(0.0, $stats->totalVolume);
    }

    #[Test]
    public function from_trades_with_empty_array_returns_empty_stats(): void
    {
        $stats = TradeStatsDTO::fromTrades([]);

        $this->assertEquals(0, $stats->totalTrades);
    }

    #[Test]
    public function from_trades_calculates_buy_sell_counts(): void
    {
        $trades = [
            ['side' => 'BUY', 'quote_quantity' => 100],
            ['side' => 'BUY', 'quote_quantity' => 150],
            ['side' => 'SELL', 'quote_quantity' => 200],
        ];

        $stats = TradeStatsDTO::fromTrades($trades);

        $this->assertEquals(3, $stats->totalTrades);
        $this->assertEquals(2, $stats->buyCount);
        $this->assertEquals(1, $stats->sellCount);
    }

    #[Test]
    public function from_trades_calculates_pnl_stats(): void
    {
        $trades = [
            ['side' => 'BUY', 'quote_quantity' => 100, 'pnl' => 10],
            ['side' => 'SELL', 'quote_quantity' => 110, 'pnl' => -5],
            ['side' => 'SELL', 'quote_quantity' => 105, 'pnl' => 15],
        ];

        $stats = TradeStatsDTO::fromTrades($trades);

        $this->assertEquals(20.0, $stats->totalPnl);
        $this->assertEquals(2, $stats->winningTrades);
        $this->assertEquals(1, $stats->losingTrades);
        $this->assertEquals(15.0, $stats->bestTrade);
        $this->assertEquals(-5.0, $stats->worstTrade);
    }

    #[Test]
    public function from_trades_calculates_win_rate(): void
    {
        $trades = [
            ['side' => 'SELL', 'quote_quantity' => 100, 'pnl' => 10],
            ['side' => 'SELL', 'quote_quantity' => 100, 'pnl' => 20],
            ['side' => 'SELL', 'quote_quantity' => 100, 'pnl' => -5],
            ['side' => 'SELL', 'quote_quantity' => 100, 'pnl' => 15],
        ];

        $stats = TradeStatsDTO::fromTrades($trades);

        // 3 gagnants sur 4 = 75%
        $this->assertEquals(75.0, $stats->winRate);
    }

    #[Test]
    public function from_trades_calculates_total_volume_and_fees(): void
    {
        $trades = [
            ['side' => 'BUY', 'quote_quantity' => 100, 'commission' => 0.1],
            ['side' => 'SELL', 'quote_quantity' => 200, 'commission' => 0.2],
        ];

        $stats = TradeStatsDTO::fromTrades($trades);

        $this->assertEquals(300.0, $stats->totalVolume);
        $this->assertEqualsWithDelta(0.3, $stats->totalFees, 0.0001);
    }

    #[Test]
    public function from_trades_with_initial_balance_calculates_pnl_percent(): void
    {
        $trades = [
            ['side' => 'SELL', 'quote_quantity' => 100, 'pnl' => 50],
        ];

        $stats = TradeStatsDTO::fromTrades($trades, initialBalance: 1000.0);

        $this->assertEquals(5.0, $stats->totalPnlPercent);
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $stats = new TradeStatsDTO(
            totalTrades: 10,
            buyCount: 5,
            sellCount: 5,
            winningTrades: 7,
            losingTrades: 3,
            winRate: 70.0,
            totalPnl: 500.50,
            totalPnlPercent: 5.005,
            averagePnl: 50.05,
            bestTrade: 200.0,
            worstTrade: -50.0,
            totalVolume: 10000.0,
            totalFees: 10.0,
        );

        $array = $stats->toArray();

        $this->assertEquals(10, $array['total_trades']);
        $this->assertEquals(5, $array['buy_count']);
        $this->assertEquals(5, $array['sell_count']);
        $this->assertEquals(70.0, $array['win_rate']);
        $this->assertEquals(500.5, $array['total_pnl']);
    }
}
