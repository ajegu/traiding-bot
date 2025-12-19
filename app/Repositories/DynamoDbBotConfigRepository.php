<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\BotConfigRepositoryInterface;
use App\Models\BotConfig;
use Aws\DynamoDb\DynamoDbClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

final class DynamoDbBotConfigRepository implements BotConfigRepositoryInterface
{
    private string $tableName;

    public function __construct(
        private readonly DynamoDbClient $dynamoDb,
    ) {
        $this->tableName = (string) config('services.dynamodb.tables.bot_config', 'trading-bot-dev-bot-config');
    }

    /**
     * Récupère la configuration du bot.
     */
    public function get(): BotConfig
    {
        try {
            $result = $this->dynamoDb->getItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'pk' => ['S' => 'CONFIG#bot'],
                    'sk' => ['S' => 'SETTINGS'],
                ],
            ]);

            if (! isset($result['Item'])) {
                // Créer une configuration par défaut si elle n'existe pas
                $config = BotConfig::default();
                $this->save($config);

                return $config;
            }

            return BotConfig::fromDynamoDB($result['Item']);
        } catch (\Exception $e) {
            Log::error('Failed to get bot config from DynamoDB', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sauvegarde la configuration du bot.
     */
    public function save(BotConfig $config): BotConfig
    {
        $config->updatedAt = Carbon::now();

        try {
            $this->dynamoDb->putItem([
                'TableName' => $this->tableName,
                'Item' => $config->toDynamoDB(),
            ]);

            Log::info('Bot config saved to DynamoDB', [
                'enabled' => $config->enabled,
                'strategy' => $config->strategy,
            ]);

            return $config;
        } catch (\Exception $e) {
            Log::error('Failed to save bot config to DynamoDB', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Active ou désactive le bot.
     */
    public function setEnabled(bool $enabled): void
    {
        try {
            $this->dynamoDb->updateItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'pk' => ['S' => 'CONFIG#bot'],
                    'sk' => ['S' => 'SETTINGS'],
                ],
                'UpdateExpression' => 'SET enabled = :enabled, updated_at = :updated_at',
                'ExpressionAttributeValues' => [
                    ':enabled' => ['BOOL' => $enabled],
                    ':updated_at' => ['S' => Carbon::now()->toIso8601String()],
                ],
            ]);

            Log::info('Bot enabled status updated', [
                'enabled' => $enabled,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to set bot enabled status', [
                'error' => $e->getMessage(),
                'enabled' => $enabled,
            ]);

            throw $e;
        }
    }

    /**
     * Vérifie si le bot est activé.
     */
    public function isEnabled(): bool
    {
        $config = $this->get();

        return $config->enabled;
    }

    /**
     * Définit la stratégie active.
     */
    public function setStrategy(string $strategy): void
    {
        try {
            $this->dynamoDb->updateItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'pk' => ['S' => 'CONFIG#bot'],
                    'sk' => ['S' => 'SETTINGS'],
                ],
                'UpdateExpression' => 'SET strategy = :strategy, updated_at = :updated_at',
                'ExpressionAttributeValues' => [
                    ':strategy' => ['S' => $strategy],
                    ':updated_at' => ['S' => Carbon::now()->toIso8601String()],
                ],
            ]);

            Log::info('Bot strategy updated', [
                'strategy' => $strategy,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to set bot strategy', [
                'error' => $e->getMessage(),
                'strategy' => $strategy,
            ]);

            throw $e;
        }
    }

    /**
     * Récupère la stratégie active.
     */
    public function getStrategy(): string
    {
        $config = $this->get();

        return $config->strategy;
    }

    /**
     * Met à jour le timestamp de la dernière exécution.
     */
    public function updateLastExecution(): void
    {
        try {
            $now = Carbon::now()->toIso8601String();

            $this->dynamoDb->updateItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'pk' => ['S' => 'CONFIG#bot'],
                    'sk' => ['S' => 'SETTINGS'],
                ],
                'UpdateExpression' => 'SET last_execution = :last_execution, updated_at = :updated_at',
                'ExpressionAttributeValues' => [
                    ':last_execution' => ['S' => $now],
                    ':updated_at' => ['S' => $now],
                ],
            ]);

            Log::debug('Bot last execution updated', [
                'last_execution' => $now,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update last execution', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Récupère le timestamp de la dernière exécution.
     */
    public function getLastExecution(): ?string
    {
        $config = $this->get();

        return $config->lastExecution;
    }
}
