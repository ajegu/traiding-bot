<?php

declare(strict_types=1);

namespace App\Services\Binance;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\DTOs\BalanceDTO;
use App\DTOs\KlineDTO;
use App\DTOs\TradeResultDTO;
use App\Enums\KlineInterval;
use App\Exceptions\BinanceApiException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class BinanceService implements BinanceServiceInterface
{
    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 1000;

    public function __construct(
        private readonly BinanceClient $client,
        private readonly TradeRepositoryInterface $tradeRepository,
    ) {}

    /**
     * Récupère le prix actuel d'une paire.
     */
    public function getCurrentPrice(string $symbol): float
    {
        return $this->executeWithRetry(
            fn () => $this->client->getPrice($symbol)
        );
    }

    /**
     * Récupère les informations ticker 24h d'une paire.
     */
    public function getTicker24h(string $symbol): array
    {
        return $this->executeWithRetry(
            fn () => $this->client->getTicker24h($symbol)
        );
    }

    /**
     * Récupère les soldes du compte.
     *
     * @return array<BalanceDTO>
     */
    public function getAccountBalances(): array
    {
        $balances = $this->executeWithRetry(
            fn () => $this->client->getBalances()
        );

        return array_map(
            fn (array $balance) => BalanceDTO::fromBinanceResponse($balance),
            array_filter($balances, fn (array $balance) => (float) $balance['free'] > 0 || (float) $balance['locked'] > 0)
        );
    }

    /**
     * Récupère le solde d'un actif spécifique.
     */
    public function getBalance(string $asset): ?BalanceDTO
    {
        $balances = $this->getAccountBalances();

        foreach ($balances as $balance) {
            if ($balance->asset === $asset) {
                return $balance;
            }
        }

        return null;
    }

    /**
     * Récupère les données de chandelier (klines).
     *
     * @return array<KlineDTO>
     */
    public function getKlines(string $symbol, KlineInterval $interval, int $limit): array
    {
        $klines = $this->executeWithRetry(
            fn () => $this->client->getKlines($symbol, $interval->value, $limit)
        );

        return array_map(
            fn (array $kline, int|string $timestamp) => KlineDTO::fromBinanceResponse($kline, $timestamp),
            $klines,
            array_keys($klines)
        );
    }

    /**
     * Passe un ordre d'achat au prix du marché.
     */
    public function marketBuy(string $symbol, float $quoteAmount): TradeResultDTO
    {
        Log::info('Executing market buy order', [
            'symbol' => $symbol,
            'quote_amount' => $quoteAmount,
        ]);

        // Vérifier le solde
        $this->checkBalance('USDT', $quoteAmount);

        try {
            $response = $this->executeWithRetry(
                fn () => $this->client->marketBuy($symbol, $quoteAmount)
            );

            $result = TradeResultDTO::fromBinanceResponse($response);

            // Enregistrer le trade
            $this->saveTrade($result);

            Log::info('Market buy order executed', $result->toArray());

            return $result;
        } catch (BinanceApiException $e) {
            Log::error('Market buy order failed', [
                'symbol' => $symbol,
                'quote_amount' => $quoteAmount,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Passe un ordre de vente au prix du marché.
     */
    public function marketSell(string $symbol, float $quantity): TradeResultDTO
    {
        Log::info('Executing market sell order', [
            'symbol' => $symbol,
            'quantity' => $quantity,
        ]);

        // Extraire l'actif de base du symbole (ex: BTC de BTCUSDT)
        $baseAsset = $this->extractBaseAsset($symbol);

        // Vérifier le solde
        $this->checkBalance($baseAsset, $quantity);

        try {
            $response = $this->executeWithRetry(
                fn () => $this->client->marketSell($symbol, $quantity)
            );

            $result = TradeResultDTO::fromBinanceResponse($response);

            // Enregistrer le trade
            $this->saveTrade($result);

            Log::info('Market sell order executed', $result->toArray());

            return $result;
        } catch (BinanceApiException $e) {
            Log::error('Market sell order failed', [
                'symbol' => $symbol,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Passe un ordre d'achat limite.
     */
    public function limitBuy(string $symbol, float $quantity, float $price): TradeResultDTO
    {
        Log::info('Executing limit buy order', [
            'symbol' => $symbol,
            'quantity' => $quantity,
            'price' => $price,
        ]);

        $quoteAmount = $quantity * $price;
        $this->checkBalance('USDT', $quoteAmount);

        try {
            $response = $this->executeWithRetry(
                fn () => $this->client->limitBuy($symbol, $quantity, $price)
            );

            $result = TradeResultDTO::fromBinanceResponse($response);

            // Enregistrer le trade
            $this->saveTrade($result);

            Log::info('Limit buy order executed', $result->toArray());

            return $result;
        } catch (BinanceApiException $e) {
            Log::error('Limit buy order failed', [
                'symbol' => $symbol,
                'quantity' => $quantity,
                'price' => $price,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Passe un ordre de vente limite.
     */
    public function limitSell(string $symbol, float $quantity, float $price): TradeResultDTO
    {
        Log::info('Executing limit sell order', [
            'symbol' => $symbol,
            'quantity' => $quantity,
            'price' => $price,
        ]);

        $baseAsset = $this->extractBaseAsset($symbol);
        $this->checkBalance($baseAsset, $quantity);

        try {
            $response = $this->executeWithRetry(
                fn () => $this->client->limitSell($symbol, $quantity, $price)
            );

            $result = TradeResultDTO::fromBinanceResponse($response);

            // Enregistrer le trade
            $this->saveTrade($result);

            Log::info('Limit sell order executed', $result->toArray());

            return $result;
        } catch (BinanceApiException $e) {
            Log::error('Limit sell order failed', [
                'symbol' => $symbol,
                'quantity' => $quantity,
                'price' => $price,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les informations d'un ordre.
     */
    public function getOrder(string $symbol, int $orderId): array
    {
        return $this->executeWithRetry(
            fn () => $this->client->getOrder($symbol, $orderId)
        );
    }

    /**
     * Annule un ordre.
     */
    public function cancelOrder(string $symbol, int $orderId): bool
    {
        try {
            $this->executeWithRetry(
                fn () => $this->client->cancelOrder($symbol, $orderId)
            );

            Log::info('Order cancelled', [
                'symbol' => $symbol,
                'order_id' => $orderId,
            ]);

            return true;
        } catch (BinanceApiException $e) {
            Log::error('Failed to cancel order', [
                'symbol' => $symbol,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Récupère les ordres ouverts.
     */
    public function getOpenOrders(?string $symbol = null): array
    {
        return $this->executeWithRetry(
            fn () => $this->client->getOpenOrders($symbol)
        );
    }

    /**
     * Récupère les informations d'échange (exchange info).
     */
    public function getExchangeInfo(string $symbol): array
    {
        return $this->executeWithRetry(
            fn () => $this->client->getExchangeInfo($symbol)
        );
    }

    /**
     * Ping le serveur Binance pour vérifier la connectivité.
     */
    public function ping(): bool
    {
        return $this->client->ping();
    }

    /**
     * Récupère le temps du serveur Binance.
     */
    public function getServerTime(): int
    {
        return $this->executeWithRetry(
            fn () => $this->client->getServerTime()
        );
    }

    /**
     * Exécute une opération avec logique de retry.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function executeWithRetry(callable $operation): mixed
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                return $operation();
            } catch (BinanceApiException $e) {
                $lastException = $e;

                if (! $e->isRetryable()) {
                    throw $e;
                }

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * $attempt;
                    usleep($delay * 1000);

                    Log::warning('Retrying Binance API call', [
                        'attempt' => $attempt,
                        'max_retries' => self::MAX_RETRIES,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Vérifie si le solde est suffisant.
     */
    private function checkBalance(string $asset, float $required): void
    {
        $balance = $this->getBalance($asset);

        if ($balance === null || $balance->free < $required) {
            throw new InsufficientBalanceException(
                message: "Insufficient {$asset} balance",
                asset: $asset,
                required: $required,
                available: $balance?->free ?? 0.0
            );
        }
    }

    /**
     * Extrait l'actif de base du symbole.
     */
    private function extractBaseAsset(string $symbol): string
    {
        // Pour BTCUSDT -> BTC, ETHUSDT -> ETH, etc.
        return str_replace(['USDT', 'BUSD', 'USDC'], '', $symbol);
    }

    /**
     * Enregistre un trade en base de données.
     */
    private function saveTrade(TradeResultDTO $result): void
    {
        try {
            $trade = new Trade(
                orderId: (int) $result->orderId,
                clientOrderId: $result->clientOrderId,
                symbol: $result->symbol,
                side: $result->side,
                type: $result->type,
                status: $result->status,
                quantity: $result->quantity,
                price: $result->price,
                quoteQuantity: $result->quoteQuantity,
                commission: $result->commission,
                commissionAsset: $result->commissionAsset,
                createdAt: Carbon::parse($result->executedAt),
            );

            $this->tradeRepository->create($trade);
        } catch (\Exception $e) {
            Log::error('Failed to save trade to database', [
                'error' => $e->getMessage(),
                'trade' => $result->toArray(),
            ]);
            // Ne pas propager l'exception pour ne pas bloquer l'ordre
        }
    }
}
