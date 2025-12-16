<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\OrderSide;
use App\Models\Trade;
use Aws\Sns\SnsClient;
use Illuminate\Support\Facades\Log;

/**
 * Service d'envoi de notifications via AWS SNS.
 */
final class SnsNotificationService
{
    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 1000;

    public function __construct(
        private readonly SnsClient $snsClient,
        private readonly string $tradeAlertsTopicArn,
        private readonly string $errorAlertsTopicArn,
        private readonly bool $enabled = true,
    ) {}

    /**
     * Publie un message sur un topic SNS.
     *
     * @param  array<string, mixed>  $message
     * @param  array<string, array<string, string>>  $attributes
     */
    public function publish(string $topicArn, array $message, array $attributes = []): bool
    {
        if (! $this->enabled) {
            Log::info('SNS notifications disabled, skipping publish');

            return false;
        }

        return $this->executeWithRetry(function () use ($topicArn, $message, $attributes) {
            $params = [
                'TopicArn' => $topicArn,
                'Message' => json_encode($message, JSON_THROW_ON_ERROR),
            ];

            if (! empty($attributes)) {
                $params['MessageAttributes'] = $this->formatMessageAttributes($attributes);
            }

            $result = $this->snsClient->publish($params);

            Log::info('SNS message published', [
                'topic_arn' => $topicArn,
                'message_id' => $result['MessageId'],
            ]);

            return true;
        });
    }

    /**
     * Publie une alerte de trade exécuté.
     */
    public function publishTradeAlert(Trade $trade): bool
    {
        $message = [
            'type' => 'TRADE_EXECUTED',
            'priority' => 'normal',
            'timestamp' => $trade->createdAt->toIso8601String(),
            'data' => [
                'trade_id' => $trade->id,
                'symbol' => $trade->symbol,
                'side' => $trade->side->value,
                'type' => $trade->type->value,
                'quantity' => $trade->quantity,
                'price' => $trade->price,
                'quote_quantity' => $trade->quoteQuantity,
                'strategy' => $trade->strategy,
                'status' => $trade->status->value,
            ],
        ];

        if ($trade->pnl !== null) {
            $message['data']['pnl'] = $trade->pnl;
            $message['data']['pnl_percent'] = $trade->pnlPercent;
        }

        $attributes = [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'TRADE_EXECUTED'],
            'symbol' => ['DataType' => 'String', 'StringValue' => $trade->symbol],
            'side' => ['DataType' => 'String', 'StringValue' => $trade->side->value],
            'priority' => ['DataType' => 'String', 'StringValue' => 'normal'],
        ];

        return $this->publish($this->tradeAlertsTopicArn, $message, $attributes);
    }

    /**
     * Publie une alerte d'erreur.
     *
     * @param  array<string, mixed>  $context
     */
    public function publishErrorAlert(string $type, string $message, array $context = []): bool
    {
        $payload = [
            'type' => 'ERROR_ALERT',
            'priority' => 'high',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'error_type' => $type,
                'message' => $message,
                'context' => $context,
            ],
        ];

        $attributes = [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'ERROR_ALERT'],
            'error_type' => ['DataType' => 'String', 'StringValue' => $type],
            'priority' => ['DataType' => 'String', 'StringValue' => 'high'],
        ];

        return $this->publish($this->errorAlertsTopicArn, $payload, $attributes);
    }

    /**
     * Publie une alerte critique.
     */
    public function publishCriticalError(\Throwable $exception): bool
    {
        $payload = [
            'type' => 'CRITICAL_ERROR',
            'priority' => 'critical',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ],
        ];

        $attributes = [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'CRITICAL_ERROR'],
            'exception_class' => ['DataType' => 'String', 'StringValue' => get_class($exception)],
            'priority' => ['DataType' => 'String', 'StringValue' => 'critical'],
        ];

        return $this->publish($this->errorAlertsTopicArn, $payload, $attributes);
    }

    /**
     * Publie une alerte de solde bas.
     */
    public function publishLowBalanceAlert(string $asset, float $balance, float $threshold): bool
    {
        $payload = [
            'type' => 'BALANCE_ALERT',
            'priority' => 'medium',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'asset' => $asset,
                'balance' => $balance,
                'threshold' => $threshold,
                'percentage' => ($balance / $threshold) * 100,
            ],
        ];

        $attributes = [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'BALANCE_ALERT'],
            'asset' => ['DataType' => 'String', 'StringValue' => $asset],
            'priority' => ['DataType' => 'String', 'StringValue' => 'medium'],
        ];

        return $this->publish($this->errorAlertsTopicArn, $payload, $attributes);
    }

    /**
     * Publie une alerte de prix.
     */
    public function publishPriceAlert(string $symbol, float $price, string $condition): bool
    {
        $payload = [
            'type' => 'PRICE_ALERT',
            'priority' => 'normal',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'symbol' => $symbol,
                'price' => $price,
                'condition' => $condition,
            ],
        ];

        $attributes = [
            'event_type' => ['DataType' => 'String', 'StringValue' => 'PRICE_ALERT'],
            'symbol' => ['DataType' => 'String', 'StringValue' => $symbol],
            'priority' => ['DataType' => 'String', 'StringValue' => 'normal'],
        ];

        return $this->publish($this->tradeAlertsTopicArn, $payload, $attributes);
    }

    /**
     * Formate les attributs de message pour SNS.
     *
     * @param  array<string, array<string, string>>  $attributes
     * @return array<string, array<string, string>>
     */
    private function formatMessageAttributes(array $attributes): array
    {
        $formatted = [];

        foreach ($attributes as $key => $value) {
            if (! isset($value['DataType']) || ! isset($value['StringValue'])) {
                throw new \InvalidArgumentException("Invalid message attribute format for key: {$key}");
            }

            $formatted[$key] = [
                'DataType' => $value['DataType'],
                'StringValue' => $value['StringValue'],
            ];
        }

        return $formatted;
    }

    /**
     * Exécute une opération avec retry en cas d'erreur.
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
            } catch (\Throwable $e) {
                $lastException = $e;

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * $attempt;
                    Log::warning('SNS API error, retrying', [
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);

                    usleep($delay * 1000);
                } else {
                    Log::error('SNS API error after all retries', [
                        'attempts' => self::MAX_RETRIES,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        throw $lastException;
    }
}
