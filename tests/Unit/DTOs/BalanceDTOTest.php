<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs;

use App\DTOs\BalanceDTO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BalanceDTOTest extends TestCase
{
    #[Test]
    public function from_binance_response_creates_dto(): void
    {
        $data = [
            'asset' => 'BTC',
            'free' => '0.5',
            'locked' => '0.1',
        ];

        $dto = BalanceDTO::fromBinanceResponse($data);

        $this->assertEquals('BTC', $dto->asset);
        $this->assertEquals(0.5, $dto->free);
        $this->assertEquals(0.1, $dto->locked);
    }

    #[Test]
    public function from_array_creates_dto(): void
    {
        $data = [
            'asset' => 'USDT',
            'free' => 1000.0,
            'locked' => 50.0,
        ];

        $dto = BalanceDTO::fromArray($data);

        $this->assertEquals('USDT', $dto->asset);
        $this->assertEquals(1000.0, $dto->free);
        $this->assertEquals(50.0, $dto->locked);
    }

    #[Test]
    public function total_returns_sum_of_free_and_locked(): void
    {
        $dto = new BalanceDTO(
            asset: 'BTC',
            free: 0.5,
            locked: 0.1,
        );

        $this->assertEquals(0.6, $dto->total());
    }

    #[Test]
    public function is_empty_returns_true_when_total_is_zero(): void
    {
        $dto = new BalanceDTO(
            asset: 'BTC',
            free: 0.0,
            locked: 0.0,
        );

        $this->assertTrue($dto->isEmpty());
    }

    #[Test]
    public function is_empty_returns_false_when_has_balance(): void
    {
        $dto = new BalanceDTO(
            asset: 'BTC',
            free: 0.001,
            locked: 0.0,
        );

        $this->assertFalse($dto->isEmpty());
    }

    #[Test]
    public function is_significant_returns_true_above_threshold(): void
    {
        $dto = new BalanceDTO(
            asset: 'BTC',
            free: 0.001,
            locked: 0.0,
        );

        $this->assertTrue($dto->isSignificant(0.0001));
    }

    #[Test]
    public function is_significant_returns_false_below_threshold(): void
    {
        $dto = new BalanceDTO(
            asset: 'BTC',
            free: 0.00001,
            locked: 0.0,
        );

        $this->assertFalse($dto->isSignificant(0.001));
    }

    #[Test]
    public function to_array_returns_correct_structure(): void
    {
        $dto = new BalanceDTO(
            asset: 'ETH',
            free: 2.5,
            locked: 0.5,
        );

        $array = $dto->toArray();

        $this->assertEquals('ETH', $array['asset']);
        $this->assertEquals(2.5, $array['free']);
        $this->assertEquals(0.5, $array['locked']);
        $this->assertEquals(3.0, $array['total']);
    }
}
