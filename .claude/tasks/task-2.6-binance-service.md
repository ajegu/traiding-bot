# Task 2.6 - BinanceService (prix, soldes, ordres market/limit)

## Objectif

Créer le service d'interaction avec l'API Binance : récupération des prix, des soldes, des chandeliers et passage d'ordres.

## Fichiers à créer

| Fichier | Description |
|---------|-------------|
| `app/Contracts/BinanceServiceInterface.php` | Interface du service |
| `app/Services/Binance/BinanceService.php` | Implémentation du service |
| `app/Services/Binance/BinanceClient.php` | Client HTTP pour Binance |
| `app/Exceptions/BinanceApiException.php` | Exception pour les erreurs API |
| `app/Exceptions/InsufficientBalanceException.php` | Exception solde insuffisant |

## API Binance

### Endpoints Utilisés

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/v3/ticker/price` | GET | Prix actuel |
| `/api/v3/ticker/24hr` | GET | Stats 24h |
| `/api/v3/klines` | GET | Chandeliers |
| `/api/v3/account` | GET | Informations compte |
| `/api/v3/order` | POST | Passer un ordre |
| `/api/v3/exchangeInfo` | GET | Informations du marché |

### URLs

| Environnement | URL |
|---------------|-----|
| Production | `https://api.binance.com` |
| Testnet | `https://testnet.binance.vision` |

## Implémentation

### 1. Interface BinanceServiceInterface

**Créer** : `app/Contracts/BinanceServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\BalanceDTO;
use App\DTOs\KlineDTO;
use App\DTOs\TradeResultDTO;
use App\Enums\KlineInterval;
use Illuminate\Support\Collection;

interface BinanceServiceInterface
{
    /**
     * Récupère le prix actuel d'un symbole.
     */
    public function getCurrentPrice(string $symbol): float;

    /**
     * Récupère les statistiques 24h d'un symbole.
     */
    public function get24hrStats(string $symbol): array;

    /**
     * Récupère les soldes du compte.
     *
     * @return Collection<int, BalanceDTO>
     */
    public function getAccountBalances(): Collection;

    /**
     * Récupère le solde d'un actif spécifique.
     */
    public function getBalance(string $asset): BalanceDTO;

    /**
     * Récupère les chandeliers (klines).
     *
     * @return Collection<int, KlineDTO>
     */
    public function getKlines(string $symbol, KlineInterval $interval, int $limit = 200): Collection;

    /**
     * Passe un ordre market d'achat.
     */
    public function marketBuy(string $symbol, float $quoteAmount): TradeResultDTO;

    /**
     * Passe un ordre market de vente.
     */
    public function marketSell(string $symbol, float $quantity): TradeResultDTO;

    /**
     * Passe un ordre limit d'achat.
     */
    public function limitBuy(string $symbol, float $quantity, float $price): TradeResultDTO;

    /**
     * Passe un ordre limit de vente.
     */
    public function limitSell(string $symbol, float $quantity, float $price): TradeResultDTO;

    /**
     * Récupère les informations d'un symbole (min qty, step size, etc.).
     */
    public function getSymbolInfo(string $symbol): array;

    /**
     * Vérifie la connectivité à l'API.
     */
    public function ping(): bool;
}
```

### 2. BinanceApiException

**Créer** : `app/Exceptions/BinanceApiException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class BinanceApiException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?array $context = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Crée une exception depuis une réponse d'erreur Binance.
     */
    public static function fromResponse(array $response, int $httpCode = 400): self
    {
        $code = $response['code'] ?? $httpCode;
        $message = $response['msg'] ?? 'Unknown Binance API error';

        return new self(
            message: $message,
            code: $code,
            context: $response,
        );
    }

    /**
     * Retourne le contexte pour le logging.
     */
    public function context(): array
    {
        return $this->context ?? [];
    }

    /**
     * Vérifie si l'erreur est récupérable (retry possible).
     */
    public function isRetryable(): bool
    {
        return in_array($this->code, [
            -1000, // Unknown error
            -1001, // Disconnected
            -1003, // Too many requests (rate limit)
        ], true);
    }
}
```

### 3. InsufficientBalanceException

**Créer** : `app/Exceptions/InsufficientBalanceException.php`

```php
<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(
        public readonly string $asset,
        public readonly float $required,
        public readonly float $available,
    ) {
        parent::__construct(
            "Insufficient {$asset} balance. Required: {$required}, Available: {$available}"
        );
    }
}
```

### 4. BinanceClient

**Créer** : `app/Services/Binance/BinanceClient.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Binance;

use App\Exceptions\BinanceApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class BinanceClient
{
    private const TIMEOUT = 30;
    private const RECV_WINDOW = 5000;

    private readonly string $baseUrl;
    private readonly ?string $apiKey;
    private readonly ?string $apiSecret;

    public function __construct()
    {
        $testnet = config('services.binance.testnet', true);

        $this->baseUrl = $testnet
            ? config('services.binance.urls.testnet', 'https://testnet.binance.vision')
            : config('services.binance.urls.api', 'https://api.binance.com');

        $this->apiKey = config('services.binance.api_key');
        $this->apiSecret = config('services.binance.api_secret');
    }

    /**
     * Effectue une requête publique (sans authentification).
     */
    public function publicRequest(string $method, string $endpoint, array $params = []): array
    {
        $response = $this->httpClient()
            ->{strtolower($method)}($this->baseUrl . $endpoint, $params);

        return $this->handleResponse($response);
    }

    /**
     * Effectue une requête signée (avec authentification).
     */
    public function signedRequest(string $method, string $endpoint, array $params = []): array
    {
        if (!$this->apiKey || !$this->apiSecret) {
            throw new BinanceApiException('API credentials not configured', -1);
        }

        $params['timestamp'] = $this->getTimestamp();
        $params['recvWindow'] = self::RECV_WINDOW;

        // Créer la signature
        $queryString = http_build_query($params);
        $signature = hash_hmac('sha256', $queryString, $this->apiSecret);
        $params['signature'] = $signature;

        $response = $this->httpClient()
            ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
            ->{strtolower($method)}($this->baseUrl . $endpoint, $params);

        return $this->handleResponse($response);
    }

    /**
     * Retourne le timestamp actuel en millisecondes.
     */
    private function getTimestamp(): int
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * Crée le client HTTP configuré.
     */
    private function httpClient(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT)
            ->connectTimeout(10)
            ->retry(3, 100, function (\Exception $exception) {
                return $exception instanceof \Illuminate\Http\Client\ConnectionException;
            });
    }

    /**
     * Traite la réponse HTTP.
     */
    private function handleResponse($response): array
    {
        $data = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Binance API error', [
                'status' => $response->status(),
                'response' => $data,
            ]);

            throw BinanceApiException::fromResponse($data, $response->status());
        }

        // Binance peut retourner une erreur avec code 200
        if (isset($data['code']) && $data['code'] < 0) {
            Log::error('Binance API error in response', [
                'response' => $data,
            ]);

            throw BinanceApiException::fromResponse($data);
        }

        return $data;
    }
}
```

### 5. BinanceService

**Créer** : `app/Services/Binance/BinanceService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services\Binance;

use App\Contracts\BinanceServiceInterface;
use App\DTOs\BalanceDTO;
use App\DTOs\KlineDTO;
use App\DTOs\TradeResultDTO;
use App\Enums\KlineInterval;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Exceptions\BinanceApiException;
use App\Exceptions\InsufficientBalanceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class BinanceService implements BinanceServiceInterface
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

    public function __construct(
        private readonly BinanceClient $client,
    ) {}

    public function getCurrentPrice(string $symbol): float
    {
        $response = $this->executeWithRetry(
            fn () => $this->client->publicRequest('GET', '/api/v3/ticker/price', [
                'symbol' => $symbol,
            ])
        );

        return (float) $response['price'];
    }

    public function get24hrStats(string $symbol): array
    {
        $response = $this->executeWithRetry(
            fn () => $this->client->publicRequest('GET', '/api/v3/ticker/24hr', [
                'symbol' => $symbol,
            ])
        );

        return [
            'symbol' => $response['symbol'],
            'price_change' => (float) $response['priceChange'],
            'price_change_percent' => (float) $response['priceChangePercent'],
            'weighted_avg_price' => (float) $response['weightedAvgPrice'],
            'prev_close_price' => (float) $response['prevClosePrice'],
            'last_price' => (float) $response['lastPrice'],
            'bid_price' => (float) $response['bidPrice'],
            'ask_price' => (float) $response['askPrice'],
            'open_price' => (float) $response['openPrice'],
            'high_price' => (float) $response['highPrice'],
            'low_price' => (float) $response['lowPrice'],
            'volume' => (float) $response['volume'],
            'quote_volume' => (float) $response['quoteVolume'],
        ];
    }

    public function getAccountBalances(): Collection
    {
        $response = $this->executeWithRetry(
            fn () => $this->client->signedRequest('GET', '/api/v3/account')
        );

        return collect($response['balances'] ?? [])
            ->map(fn (array $balance) => BalanceDTO::fromBinanceResponse($balance))
            ->filter(fn (BalanceDTO $balance) => $balance->isSignificant());
    }

    public function getBalance(string $asset): BalanceDTO
    {
        $balances = $this->getAccountBalances();

        $balance = $balances->first(fn (BalanceDTO $b) => $b->asset === $asset);

        return $balance ?? new BalanceDTO($asset, 0.0, 0.0);
    }

    public function getKlines(string $symbol, KlineInterval $interval, int $limit = 200): Collection
    {
        $response = $this->executeWithRetry(
            fn () => $this->client->publicRequest('GET', '/api/v3/klines', [
                'symbol' => $symbol,
                'interval' => $interval->value,
                'limit' => $limit,
            ])
        );

        return collect($response)
            ->map(fn (array $kline) => KlineDTO::fromBinanceResponse($kline));
    }

    public function marketBuy(string $symbol, float $quoteAmount): TradeResultDTO
    {
        Log::info('Executing market buy', [
            'symbol' => $symbol,
            'quote_amount' => $quoteAmount,
        ]);

        // Vérifier le solde USDT
        $balance = $this->getBalance('USDT');
        if ($balance->free < $quoteAmount) {
            throw new InsufficientBalanceException('USDT', $quoteAmount, $balance->free);
        }

        $response = $this->executeWithRetry(
            fn () => $this->client->signedRequest('POST', '/api/v3/order', [
                'symbol' => $symbol,
                'side' => OrderSide::Buy->value,
                'type' => OrderType::Market->value,
                'quoteOrderQty' => $this->formatQuantity($quoteAmount),
            ])
        );

        $result = TradeResultDTO::fromBinanceResponse($response);

        Log::info('Market buy executed', $result->toArray());

        return $result;
    }

    public function marketSell(string $symbol, float $quantity): TradeResultDTO
    {
        Log::info('Executing market sell', [
            'symbol' => $symbol,
            'quantity' => $quantity,
        ]);

        // Vérifier le solde de la crypto (ex: BTC pour BTCUSDT)
        $baseAsset = $this->getBaseAsset($symbol);
        $balance = $this->getBalance($baseAsset);

        if ($balance->free < $quantity) {
            throw new InsufficientBalanceException($baseAsset, $quantity, $balance->free);
        }

        // Ajuster la quantité selon le step size
        $symbolInfo = $this->getSymbolInfo($symbol);
        $adjustedQuantity = $this->adjustQuantity($quantity, $symbolInfo);

        $response = $this->executeWithRetry(
            fn () => $this->client->signedRequest('POST', '/api/v3/order', [
                'symbol' => $symbol,
                'side' => OrderSide::Sell->value,
                'type' => OrderType::Market->value,
                'quantity' => $adjustedQuantity,
            ])
        );

        $result = TradeResultDTO::fromBinanceResponse($response);

        Log::info('Market sell executed', $result->toArray());

        return $result;
    }

    public function limitBuy(string $symbol, float $quantity, float $price): TradeResultDTO
    {
        Log::info('Executing limit buy', [
            'symbol' => $symbol,
            'quantity' => $quantity,
            'price' => $price,
        ]);

        $symbolInfo = $this->getSymbolInfo($symbol);
        $adjustedQuantity = $this->adjustQuantity($quantity, $symbolInfo);
        $adjustedPrice = $this->adjustPrice($price, $symbolInfo);

        $response = $this->executeWithRetry(
            fn () => $this->client->signedRequest('POST', '/api/v3/order', [
                'symbol' => $symbol,
                'side' => OrderSide::Buy->value,
                'type' => OrderType::Limit->value,
                'timeInForce' => 'GTC',
                'quantity' => $adjustedQuantity,
                'price' => $adjustedPrice,
            ])
        );

        $result = TradeResultDTO::fromBinanceResponse($response);

        Log::info('Limit buy created', $result->toArray());

        return $result;
    }

    public function limitSell(string $symbol, float $quantity, float $price): TradeResultDTO
    {
        Log::info('Executing limit sell', [
            'symbol' => $symbol,
            'quantity' => $quantity,
            'price' => $price,
        ]);

        $symbolInfo = $this->getSymbolInfo($symbol);
        $adjustedQuantity = $this->adjustQuantity($quantity, $symbolInfo);
        $adjustedPrice = $this->adjustPrice($price, $symbolInfo);

        $response = $this->executeWithRetry(
            fn () => $this->client->signedRequest('POST', '/api/v3/order', [
                'symbol' => $symbol,
                'side' => OrderSide::Sell->value,
                'type' => OrderType::Limit->value,
                'timeInForce' => 'GTC',
                'quantity' => $adjustedQuantity,
                'price' => $adjustedPrice,
            ])
        );

        $result = TradeResultDTO::fromBinanceResponse($response);

        Log::info('Limit sell created', $result->toArray());

        return $result;
    }

    public function getSymbolInfo(string $symbol): array
    {
        static $cache = [];

        if (isset($cache[$symbol])) {
            return $cache[$symbol];
        }

        $response = $this->executeWithRetry(
            fn () => $this->client->publicRequest('GET', '/api/v3/exchangeInfo', [
                'symbol' => $symbol,
            ])
        );

        $symbolInfo = $response['symbols'][0] ?? null;

        if (!$symbolInfo) {
            throw new BinanceApiException("Symbol {$symbol} not found", -1);
        }

        $filters = collect($symbolInfo['filters']);

        $cache[$symbol] = [
            'symbol' => $symbolInfo['symbol'],
            'status' => $symbolInfo['status'],
            'baseAsset' => $symbolInfo['baseAsset'],
            'quoteAsset' => $symbolInfo['quoteAsset'],
            'lotSize' => $this->extractFilter($filters, 'LOT_SIZE'),
            'priceFilter' => $this->extractFilter($filters, 'PRICE_FILTER'),
            'minNotional' => $this->extractFilter($filters, 'NOTIONAL'),
        ];

        return $cache[$symbol];
    }

    public function ping(): bool
    {
        try {
            $this->client->publicRequest('GET', '/api/v3/ping');
            return true;
        } catch (\Exception $e) {
            Log::warning('Binance ping failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Exécute une opération avec retry automatique.
     *
     * @template T
     * @param callable(): T $operation
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

                if (!$e->isRetryable()) {
                    throw $e;
                }

                if ($attempt < self::MAX_RETRIES) {
                    Log::warning('Binance API retry', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);

                    usleep(self::RETRY_DELAY_MS * 1000 * $attempt);
                }
            }
        }

        throw $lastException;
    }

    /**
     * Extrait l'actif de base d'un symbole (ex: BTC pour BTCUSDT).
     */
    private function getBaseAsset(string $symbol): string
    {
        $info = $this->getSymbolInfo($symbol);
        return $info['baseAsset'];
    }

    /**
     * Extrait un filtre des informations du symbole.
     */
    private function extractFilter(Collection $filters, string $filterType): array
    {
        $filter = $filters->firstWhere('filterType', $filterType);
        return $filter ?? [];
    }

    /**
     * Ajuste la quantité selon le step size.
     */
    private function adjustQuantity(float $quantity, array $symbolInfo): string
    {
        $lotSize = $symbolInfo['lotSize'] ?? [];
        $stepSize = (float) ($lotSize['stepSize'] ?? 0.00001);
        $minQty = (float) ($lotSize['minQty'] ?? 0);

        // Arrondir au step size inférieur
        $precision = $this->getPrecision($stepSize);
        $adjusted = floor($quantity / $stepSize) * $stepSize;

        // Vérifier le minimum
        if ($adjusted < $minQty) {
            throw new BinanceApiException(
                "Quantity {$adjusted} below minimum {$minQty}",
                -1013
            );
        }

        return number_format($adjusted, $precision, '.', '');
    }

    /**
     * Ajuste le prix selon le tick size.
     */
    private function adjustPrice(float $price, array $symbolInfo): string
    {
        $priceFilter = $symbolInfo['priceFilter'] ?? [];
        $tickSize = (float) ($priceFilter['tickSize'] ?? 0.01);

        $precision = $this->getPrecision($tickSize);
        $adjusted = floor($price / $tickSize) * $tickSize;

        return number_format($adjusted, $precision, '.', '');
    }

    /**
     * Formate une quantité pour l'API.
     */
    private function formatQuantity(float $value): string
    {
        return number_format($value, 8, '.', '');
    }

    /**
     * Calcule la précision décimale d'un nombre.
     */
    private function getPrecision(float $value): int
    {
        if ($value >= 1) {
            return 0;
        }

        $str = rtrim(sprintf('%.10f', $value), '0');
        $parts = explode('.', $str);

        return isset($parts[1]) ? strlen($parts[1]) : 0;
    }
}
```

### 6. Enregistrement dans le Service Provider

**Modifier** : `app/Providers/AppServiceProvider.php`

```php
// Ajouter dans les bindings
public array $bindings = [
    // ... autres bindings
    BinanceServiceInterface::class => BinanceService::class,
];

// Ajouter dans register()
public function register(): void
{
    // ... code existant

    $this->app->singleton(BinanceClient::class);
}
```

## Tests

**Créer** : `tests/Unit/Services/Binance/BinanceServiceTest.php`

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Binance;

use App\Services\Binance\BinanceClient;
use App\Services\Binance\BinanceService;
use App\Enums\KlineInterval;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class BinanceServiceTest extends TestCase
{
    private BinanceService $service;
    private MockInterface $clientMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientMock = Mockery::mock(BinanceClient::class);
        $this->service = new BinanceService($this->clientMock);
    }

    public function test_get_current_price(): void
    {
        $this->clientMock
            ->shouldReceive('publicRequest')
            ->with('GET', '/api/v3/ticker/price', ['symbol' => 'BTCUSDT'])
            ->once()
            ->andReturn(['price' => '42500.00']);

        $price = $this->service->getCurrentPrice('BTCUSDT');

        $this->assertEquals(42500.0, $price);
    }

    public function test_get_klines(): void
    {
        $klineData = [
            [1704067200000, '42000', '42500', '41800', '42300', '100', 1704070800000, '4200000', 1000],
            [1704070800000, '42300', '42800', '42200', '42600', '150', 1704074400000, '6390000', 1200],
        ];

        $this->clientMock
            ->shouldReceive('publicRequest')
            ->with('GET', '/api/v3/klines', [
                'symbol' => 'BTCUSDT',
                'interval' => '5m',
                'limit' => 200,
            ])
            ->once()
            ->andReturn($klineData);

        $klines = $this->service->getKlines('BTCUSDT', KlineInterval::FiveMinutes);

        $this->assertCount(2, $klines);
        $this->assertEquals(42300.0, $klines->first()->close);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
```

## Utilisation

```php
use App\Contracts\BinanceServiceInterface;

class TradingService
{
    public function __construct(
        private readonly BinanceServiceInterface $binance,
    ) {}

    public function analyze(string $symbol): void
    {
        // Prix actuel
        $price = $this->binance->getCurrentPrice($symbol);

        // Chandeliers pour calcul RSI
        $klines = $this->binance->getKlines($symbol, KlineInterval::FiveMinutes, 200);

        // Soldes
        $balances = $this->binance->getAccountBalances();

        // Passer un ordre
        $result = $this->binance->marketBuy('BTCUSDT', 100.0);
    }
}
```

## Dépendances

- **Prérequis** : Tâches 2.3 (Enums), 2.4 (DTOs)
- **Configuration** : Tâche 2.2 (config/services.php avec Binance)
- **Utilisé par** : Tâches 2.7 (Indicators), 2.8 (TradingStrategy), 2.11 (ReportService)

## Checklist

- [ ] Créer `app/Contracts/BinanceServiceInterface.php`
- [ ] Créer `app/Exceptions/BinanceApiException.php`
- [ ] Créer `app/Exceptions/InsufficientBalanceException.php`
- [ ] Créer `app/Services/Binance/BinanceClient.php`
- [ ] Créer `app/Services/Binance/BinanceService.php`
- [ ] Enregistrer dans `AppServiceProvider`
- [ ] Créer les tests unitaires avec mocks
- [ ] Tester sur Binance Testnet
- [ ] Vérifier avec `vendor/bin/pint`
