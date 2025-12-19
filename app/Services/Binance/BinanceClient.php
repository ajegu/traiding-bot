<?php

declare(strict_types=1);

namespace App\Services\Binance;

use App\Exceptions\BinanceApiException;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper pour la bibliothèque jaggedsoft/php-binance-api.
 */
final class BinanceClient
{
    private \Binance\API $api;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly bool $testnet = true,
    ) {
        // Le 3ème paramètre active le testnet directement dans le constructeur
        $this->api = new \Binance\API($this->apiKey, $this->apiSecret, $this->testnet);
    }

    /**
     * Récupère le prix actuel d'un symbole.
     */
    public function getPrice(string $symbol): float
    {
        try {
            $ticker = $this->api->price($symbol);

            if (! isset($ticker[$symbol])) {
                throw new BinanceApiException(
                    "Price not found for symbol: {$symbol}",
                    404,
                    ['symbol' => $symbol]
                );
            }

            return (float) $ticker[$symbol];
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Récupère les informations ticker 24h.
     */
    public function getTicker24h(string $symbol): array
    {
        try {
            $ticker = $this->api->ticker24hr($symbol);

            if (empty($ticker)) {
                throw new BinanceApiException(
                    "24h ticker not found for symbol: {$symbol}",
                    404,
                    ['symbol' => $symbol]
                );
            }

            return $ticker;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Récupère les soldes du compte.
     */
    public function getBalances(): array
    {
        try {
            $account = $this->api->account();

            return $account['balances'] ?? [];
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Récupère les données de chandelier (klines).
     */
    public function getKlines(string $symbol, string $interval, int $limit): array
    {
        try {
            $klines = $this->api->candlesticks($symbol, $interval, $limit);

            if (empty($klines)) {
                return [];
            }

            return $klines;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Passe un ordre d'achat au marché.
     */
    public function marketBuy(string $symbol, float $quoteAmount): array
    {
        try {
            $order = $this->api->marketBuyQuote($symbol, $quoteAmount);

            if (! isset($order['orderId'])) {
                throw new BinanceApiException(
                    'Market buy order failed: invalid response',
                    500,
                    ['symbol' => $symbol, 'quote_amount' => $quoteAmount]
                );
            }

            return $order;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Passe un ordre de vente au marché.
     */
    public function marketSell(string $symbol, float $quantity): array
    {
        try {
            $order = $this->api->marketSell($symbol, $quantity);

            if (! isset($order['orderId'])) {
                throw new BinanceApiException(
                    'Market sell order failed: invalid response',
                    500,
                    ['symbol' => $symbol, 'quantity' => $quantity]
                );
            }

            return $order;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Passe un ordre d'achat limite.
     */
    public function limitBuy(string $symbol, float $quantity, float $price): array
    {
        try {
            $order = $this->api->buy($symbol, $quantity, $price);

            if (! isset($order['orderId'])) {
                throw new BinanceApiException(
                    'Limit buy order failed: invalid response',
                    500,
                    ['symbol' => $symbol, 'quantity' => $quantity, 'price' => $price]
                );
            }

            return $order;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Passe un ordre de vente limite.
     */
    public function limitSell(string $symbol, float $quantity, float $price): array
    {
        try {
            $order = $this->api->sell($symbol, $quantity, $price);

            if (! isset($order['orderId'])) {
                throw new BinanceApiException(
                    'Limit sell order failed: invalid response',
                    500,
                    ['symbol' => $symbol, 'quantity' => $quantity, 'price' => $price]
                );
            }

            return $order;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Récupère les informations d'un ordre.
     */
    public function getOrder(string $symbol, int $orderId): array
    {
        try {
            $order = $this->api->orderStatus($symbol, $orderId);

            if (empty($order)) {
                throw new BinanceApiException(
                    "Order not found: {$orderId}",
                    404,
                    ['symbol' => $symbol, 'order_id' => $orderId]
                );
            }

            return $order;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Annule un ordre.
     */
    public function cancelOrder(string $symbol, int $orderId): array
    {
        try {
            $result = $this->api->cancel($symbol, $orderId);

            return $result;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Récupère les ordres ouverts.
     */
    public function getOpenOrders(?string $symbol = null): array
    {
        try {
            $orders = $symbol !== null
                ? $this->api->openOrders($symbol)
                : $this->api->openOrders();

            return $orders;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Récupère les informations d'échange.
     */
    public function getExchangeInfo(string $symbol): array
    {
        try {
            $info = $this->api->exchangeInfo();

            if (empty($info['symbols'])) {
                throw new BinanceApiException('Exchange info not available', 500);
            }

            // Chercher le symbole spécifique
            foreach ($info['symbols'] as $symbolInfo) {
                if ($symbolInfo['symbol'] === $symbol) {
                    return $symbolInfo;
                }
            }

            throw new BinanceApiException(
                "Symbol not found in exchange info: {$symbol}",
                404,
                ['symbol' => $symbol]
            );
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Ping le serveur Binance.
     */
    public function ping(): bool
    {
        try {
            $result = $this->api->ping();

            return $result === [];
        } catch (\Exception $e) {
            Log::warning('Binance ping failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Récupère le temps du serveur.
     */
    public function getServerTime(): int
    {
        try {
            $time = $this->api->time();

            return $time['serverTime'] ?? 0;
        } catch (\Exception $e) {
            $this->handleException($e, __METHOD__);
            throw $e;
        }
    }

    /**
     * Gère les exceptions de l'API Binance.
     */
    private function handleException(\Exception $e, string $method): void
    {
        Log::error('Binance API error', [
            'method' => $method,
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Si ce n'est pas déjà une BinanceApiException, la créer
        if (! $e instanceof BinanceApiException) {
            throw new BinanceApiException(
                $e->getMessage(),
                $e->getCode(),
                ['original_exception' => get_class($e)],
                $e
            );
        }
    }
}
