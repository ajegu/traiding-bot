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
use App\Services\Notification\SnsNotificationService;
use App\Services\Notification\TelegramService;
use App\Services\Report\ReportService;
use App\Services\Trading\TradingService;
use Aws\DynamoDb\DynamoDbClient;
use Aws\Sns\SnsClient;
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
            $config = $this->getAwsClientConfig();

            // Local endpoint for development (DynamoDB Local)
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

        // Register Telegram Service as singleton
        $this->app->singleton(TelegramService::class, function () {
            return new TelegramService(
                botToken: (string) config('services.telegram.bot_token', ''),
                chatId: (string) config('services.telegram.chat_id', ''),
                enabled: (bool) config('services.telegram.enabled', false),
            );
        });

        // Register SNS Client as singleton
        $this->app->singleton(SnsClient::class, function () {
            return new SnsClient($this->getAwsClientConfig());
        });

        // Register SNS Notification Service as singleton
        $this->app->singleton(SnsNotificationService::class, function ($app) {
            return new SnsNotificationService(
                snsClient: $app->make(SnsClient::class),
                tradeAlertsTopicArn: (string) config('services.sns.topics.trade_alerts', ''),
                errorAlertsTopicArn: (string) config('services.sns.topics.error_alerts', ''),
                enabled: (bool) config('services.sns.enabled', true),
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

    /**
     * Get the base AWS client configuration.
     *
     * Supports both SSO profiles and explicit credentials.
     *
     * @return array<string, mixed>
     */
    private function getAwsClientConfig(): array
    {
        /** @var string $region */
        $region = config('aws.region') ?? config('services.aws.region') ?? 'eu-west-3';

        $config = [
            'region' => $region,
            'version' => 'latest',
        ];

        // Use AWS profile if configured (for SSO)
        $profile = config('aws.profile');
        if (is_string($profile) && $profile !== '') {
            $config['profile'] = $profile;

            return $config;
        }

        // Otherwise use explicit credentials if available
        $credentials = config('aws.credentials');
        if (is_array($credentials) && ! empty($credentials['key']) && ! empty($credentials['secret'])) {
            $config['credentials'] = $credentials;
        }

        return $config;
    }
}
