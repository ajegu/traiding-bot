<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\BalanceDTO;
use App\DTOs\KlineDTO;
use App\DTOs\TradeResultDTO;
use App\Enums\KlineInterval;

interface BinanceServiceInterface
{
    /**
     * Récupère le prix actuel d'une paire.
     */
    public function getCurrentPrice(string $symbol): float;

    /**
     * Récupère les informations ticker 24h d'une paire.
     *
     * @return array<string, mixed>
     */
    public function getTicker24h(string $symbol): array;

    /**
     * Récupère les soldes du compte.
     *
     * @return array<BalanceDTO>
     */
    public function getAccountBalances(): array;

    /**
     * Récupère le solde d'un actif spécifique.
     */
    public function getBalance(string $asset): ?BalanceDTO;

    /**
     * Récupère les données de chandelier (klines).
     *
     * @return array<KlineDTO>
     */
    public function getKlines(string $symbol, KlineInterval $interval, int $limit): array;

    /**
     * Passe un ordre d'achat au prix du marché.
     */
    public function marketBuy(string $symbol, float $quoteAmount): TradeResultDTO;

    /**
     * Passe un ordre de vente au prix du marché.
     */
    public function marketSell(string $symbol, float $quantity): TradeResultDTO;

    /**
     * Passe un ordre d'achat limite.
     */
    public function limitBuy(string $symbol, float $quantity, float $price): TradeResultDTO;

    /**
     * Passe un ordre de vente limite.
     */
    public function limitSell(string $symbol, float $quantity, float $price): TradeResultDTO;

    /**
     * Récupère les informations d'un ordre.
     *
     * @return array<string, mixed>
     */
    public function getOrder(string $symbol, int $orderId): array;

    /**
     * Annule un ordre.
     */
    public function cancelOrder(string $symbol, int $orderId): bool;

    /**
     * Récupère les ordres ouverts.
     *
     * @return array<array<string, mixed>>
     */
    public function getOpenOrders(?string $symbol = null): array;

    /**
     * Récupère les informations d'échange (exchange info).
     *
     * @return array<string, mixed>
     */
    public function getExchangeInfo(string $symbol): array;

    /**
     * Ping le serveur Binance pour vérifier la connectivité.
     */
    public function ping(): bool;

    /**
     * Récupère le temps du serveur Binance.
     */
    public function getServerTime(): int;
}
