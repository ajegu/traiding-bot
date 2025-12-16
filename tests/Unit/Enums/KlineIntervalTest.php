<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\KlineInterval;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KlineIntervalTest extends TestCase
{
    #[Test]
    public function one_minute_has_correct_value(): void
    {
        $this->assertEquals('1m', KlineInterval::OneMinute->value);
    }

    #[Test]
    public function five_minutes_has_correct_value(): void
    {
        $this->assertEquals('5m', KlineInterval::FiveMinutes->value);
    }

    #[Test]
    public function one_hour_has_correct_value(): void
    {
        $this->assertEquals('1h', KlineInterval::OneHour->value);
    }

    #[Test]
    public function one_day_has_correct_value(): void
    {
        $this->assertEquals('1d', KlineInterval::OneDay->value);
    }

    #[Test]
    public function one_minute_equals_60_seconds(): void
    {
        $this->assertEquals(60, KlineInterval::OneMinute->toSeconds());
    }

    #[Test]
    public function five_minutes_equals_300_seconds(): void
    {
        $this->assertEquals(300, KlineInterval::FiveMinutes->toSeconds());
    }

    #[Test]
    public function one_hour_equals_3600_seconds(): void
    {
        $this->assertEquals(3600, KlineInterval::OneHour->toSeconds());
    }

    #[Test]
    public function one_day_equals_86400_seconds(): void
    {
        $this->assertEquals(86400, KlineInterval::OneDay->toSeconds());
    }

    #[Test]
    public function default_is_five_minutes(): void
    {
        $this->assertEquals(KlineInterval::FiveMinutes, KlineInterval::default());
    }

    #[Test]
    public function all_intervals_have_labels(): void
    {
        foreach (KlineInterval::cases() as $interval) {
            $this->assertNotEmpty($interval->label());
        }
    }

    #[Test]
    public function all_intervals_have_positive_seconds(): void
    {
        foreach (KlineInterval::cases() as $interval) {
            $this->assertGreaterThan(0, $interval->toSeconds());
        }
    }

    #[Test]
    public function intervals_are_sorted_by_duration(): void
    {
        $intervals = [
            KlineInterval::OneMinute,
            KlineInterval::FiveMinutes,
            KlineInterval::OneHour,
            KlineInterval::OneDay,
        ];

        $previousSeconds = 0;
        foreach ($intervals as $interval) {
            $this->assertGreaterThan($previousSeconds, $interval->toSeconds());
            $previousSeconds = $interval->toSeconds();
        }
    }
}
