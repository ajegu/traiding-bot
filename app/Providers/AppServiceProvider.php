<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\BinanceServiceInterface;
use App\Contracts\BotConfigRepositoryInterface;
use App\Contracts\NotificationServiceInterface;
use App\Contracts\ReportRepositoryInterface;
use App\Contracts\ReportServiceInterface;
use App\Contracts\TradeRepositoryInterface;
use App\Contracts\TradingServiceInterface;
use App\Repositories\DynamoDbBotConfigRepository;
use App\Repositories\DynamoDbReportRepository;
use App\Repositories\DynamoDbTradeRepository;
use App\Services\Binance\BinanceClient;
use App\Services\Binance\BinanceService;
use App\Services\Notification\NotificationService;
use App\Services\Report\ReportService;
use App\Services\Trading\TradingService;
use Aws\DynamoDb\DynamoDbClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        BinanceServiceInterface::class => BinanceService::class,
        TradingServiceInterface::class => TradingService::class,
        NotificationServiceInterface::class => NotificationService::class,
        BotConfigRepositoryInterface::class => DynamoDbBotConfigRepository::class,
        TradeRepositoryInterface::class => DynamoDbTradeRepository::class,
        ReportRepositoryInterface::class => DynamoDbReportRepository::class,
        ReportServiceInterface::class => ReportService::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register DynamoDB Client as singleton
        $this->app->singleton(DynamoDbClient::class, function () {
            /** @var string $region */
            $region = config('services.dynamodb.region') ?? config('services.aws.region') ?? 'eu-west-3';

            $config = [
                'region' => $region,
                'version' => 'latest',
            ];

            // Use credentials from config if available
            $awsKey = config('services.aws.key');
            $awsSecret = config('services.aws.secret');
            if (is_string($awsKey) && is_string($awsSecret) && $awsKey !== '' && $awsSecret !== '') {
                $config['credentials'] = [
                    'key' => $awsKey,
                    'secret' => $awsSecret,
                ];
            }

            // Local endpoint for development
            $endpoint = config('services.dynamodb.endpoint');
            if (is_string($endpoint) && $endpoint !== '') {
                $config['endpoint'] = $endpoint;
            }

            return new DynamoDbClient($config);
        });

        // Register Binance Client as singleton
        $this->app->singleton(BinanceClient::class, function () {
            return new BinanceClient(
                apiKey: (string) config('services.binance.api_key', ''),
                apiSecret: (string) config('services.binance.api_secret', ''),
                testnet: (bool) config('services.binance.testnet', true),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
