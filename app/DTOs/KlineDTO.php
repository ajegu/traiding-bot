<?php

declare(strict_types=1);

namespace App\DTOs;

use DateTimeImmutable;

final readonly class KlineDTO
{
    public function __construct(
        public DateTimeImmutable $openTime,
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public float $volume,
        public DateTimeImmutable $closeTime,
        public float $quoteVolume,
        public int $numberOfTrades,
    ) {}

    /**
     * Crée une instance depuis une réponse Binance.
     *
     * Format Binance kline:
     * [0] Open time, [1] Open, [2] High, [3] Low, [4] Close, [5] Volume,
     * [6] Close time, [7] Quote asset volume, [8] Number of trades,
     * [9] Taker buy base volume, [10] Taker buy quote volume, [11] Ignore
     */
    public static function fromBinanceResponse(array $kline): self
    {
        return new self(
            openTime: (new DateTimeImmutable)->setTimestamp((int) ($kline[0] / 1000)),
            open: (float) $kline[1],
            high: (float) $kline[2],
            low: (float) $kline[3],
            close: (float) $kline[4],
            volume: (float) $kline[5],
            closeTime: (new DateTimeImmutable)->setTimestamp((int) ($kline[6] / 1000)),
            quoteVolume: (float) $kline[7],
            numberOfTrades: (int) $kline[8],
        );
    }

    /**
     * Crée une instance depuis un tableau.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            openTime: new DateTimeImmutable($data['open_time']),
            open: (float) $data['open'],
            high: (float) $data['high'],
            low: (float) $data['low'],
            close: (float) $data['close'],
            volume: (float) $data['volume'],
            closeTime: new DateTimeImmutable($data['close_time']),
            quoteVolume: (float) $data['quote_volume'],
            numberOfTrades: (int) $data['number_of_trades'],
        );
    }

    /**
     * Retourne le prix médian.
     */
    public function medianPrice(): float
    {
        return ($this->high + $this->low) / 2;
    }

    /**
     * Retourne le prix typique (HLC/3).
     */
    public function typicalPrice(): float
    {
        return ($this->high + $this->low + $this->close) / 3;
    }

    /**
     * Vérifie si c'est un chandelier haussier.
     */
    public function isBullish(): bool
    {
        return $this->close > $this->open;
    }

    /**
     * Vérifie si c'est un chandelier baissier.
     */
    public function isBearish(): bool
    {
        return $this->close < $this->open;
    }

    /**
     * Retourne la variation en pourcentage.
     */
    public function changePercent(): float
    {
        if ($this->open === 0.0) {
            return 0.0;
        }

        return (($this->close - $this->open) / $this->open) * 100;
    }

    /**
     * Convertit en tableau.
     */
    public function toArray(): array
    {
        return [
            'open_time' => $this->openTime->format('c'),
            'open' => $this->open,
            'high' => $this->high,
            'low' => $this->low,
            'close' => $this->close,
            'volume' => $this->volume,
            'close_time' => $this->closeTime->format('c'),
            'quote_volume' => $this->quoteVolume,
            'number_of_trades' => $this->numberOfTrades,
        ];
    }
}
