<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\TradeRepositoryInterface;
use App\DTOs\TradeStatsDTO;
use App\Models\Trade;
use Aws\DynamoDb\DynamoDbClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class DynamoDbTradeRepository implements TradeRepositoryInterface
{
    private const GSI_SYMBOL_DATE = 'gsi1-symbol-date';
    private const GSI_DATE = 'gsi2-date';
    private const GSI_STATUS = 'gsi3-status';

    private string $tableName;

    public function __construct(
        private readonly DynamoDbClient $dynamoDb,
    ) {
        $this->tableName = (string) config('services.dynamodb.tables.trades', 'trading-bot-dev-trades');
    }

    /**
     * Crée un nouveau trade.
     */
    public function create(Trade $trade): Trade
    {
        try {
            $this->dynamoDb->putItem([
                'TableName' => $this->tableName,
                'Item' => $trade->toDynamoDB(),
            ]);

            Log::info('Trade created in DynamoDB', [
                'trade_id' => $trade->id,
                'symbol' => $trade->symbol,
                'side' => $trade->side->value,
            ]);

            return $trade;
        } catch (\Exception $e) {
            Log::error('Failed to create trade in DynamoDB', [
                'error' => $e->getMessage(),
                'trade_id' => $trade->id,
            ]);

            throw $e;
        }
    }

    /**
     * Trouve un trade par son ID.
     */
    public function findById(string $id): ?Trade
    {
        try {
            $result = $this->dynamoDb->getItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'pk' => ['S' => "TRADE#{$id}"],
                    'sk' => ['S' => 'METADATA'],
                ],
            ]);

            if (! isset($result['Item'])) {
                return null;
            }

            return Trade::fromDynamoDB($result['Item']);
        } catch (\Exception $e) {
            Log::error('Failed to find trade by ID', [
                'error' => $e->getMessage(),
                'trade_id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * Met à jour un trade existant.
     */
    public function update(Trade $trade): Trade
    {
        $trade->updatedAt = Carbon::now();

        try {
            $this->dynamoDb->putItem([
                'TableName' => $this->tableName,
                'Item' => $trade->toDynamoDB(),
            ]);

            Log::info('Trade updated in DynamoDB', [
                'trade_id' => $trade->id,
            ]);

            return $trade;
        } catch (\Exception $e) {
            Log::error('Failed to update trade in DynamoDB', [
                'error' => $e->getMessage(),
                'trade_id' => $trade->id,
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les trades d'une date spécifique.
     */
    public function findByDate(Carbon $date, int $limit = 50): Collection
    {
        try {
            $result = $this->dynamoDb->query([
                'TableName' => $this->tableName,
                'IndexName' => self::GSI_DATE,
                'KeyConditionExpression' => 'gsi2pk = :date',
                'ExpressionAttributeValues' => [
                    ':date' => ['S' => 'DATE#'.$date->format('Y-m-d')],
                ],
                'ScanIndexForward' => false, // Plus récents d'abord
                'Limit' => $limit,
            ]);

            return $this->mapResultsToCollection($result['Items'] ?? []);
        } catch (\Exception $e) {
            Log::error('Failed to find trades by date', [
                'error' => $e->getMessage(),
                'date' => $date->format('Y-m-d'),
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les trades d'une période.
     *
     * Note: Itère sur chaque jour car le GSI utilise la date comme partition key.
     */
    public function findByDateRange(Carbon $from, Carbon $to): Collection
    {
        try {
            $allTrades = collect();
            $currentDate = $from->copy()->startOfDay();
            $endDate = $to->copy()->startOfDay();

            // Itérer sur chaque jour de la période
            while ($currentDate->lte($endDate)) {
                $dayTrades = $this->findByDate($currentDate, 1000);
                $allTrades = $allTrades->merge($dayTrades);
                $currentDate->addDay();
            }

            // Trier par date décroissante
            return $allTrades->sortByDesc(fn (Trade $trade) => $trade->createdAt);
        } catch (\Exception $e) {
            Log::error('Failed to find trades by date range', [
                'error' => $e->getMessage(),
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les trades d'un symbole.
     */
    public function findBySymbol(string $symbol, int $limit = 50): Collection
    {
        try {
            $result = $this->dynamoDb->query([
                'TableName' => $this->tableName,
                'IndexName' => self::GSI_SYMBOL_DATE,
                'KeyConditionExpression' => 'gsi1pk = :symbol',
                'ExpressionAttributeValues' => [
                    ':symbol' => ['S' => "SYMBOL#{$symbol}"],
                ],
                'ScanIndexForward' => false,
                'Limit' => $limit,
            ]);

            return $this->mapResultsToCollection($result['Items'] ?? []);
        } catch (\Exception $e) {
            Log::error('Failed to find trades by symbol', [
                'error' => $e->getMessage(),
                'symbol' => $symbol,
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les trades par statut.
     */
    public function findByStatus(string $status): Collection
    {
        try {
            $result = $this->dynamoDb->query([
                'TableName' => $this->tableName,
                'IndexName' => self::GSI_STATUS,
                'KeyConditionExpression' => 'gsi3pk = :status',
                'ExpressionAttributeValues' => [
                    ':status' => ['S' => "STATUS#{$status}"],
                ],
                'ScanIndexForward' => false,
            ]);

            return $this->mapResultsToCollection($result['Items'] ?? []);
        } catch (\Exception $e) {
            Log::error('Failed to find trades by status', [
                'error' => $e->getMessage(),
                'status' => $status,
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les positions ouvertes (achats sans vente associée).
     */
    public function getOpenPositions(?string $symbol = null): Collection
    {
        try {
            $params = [
                'TableName' => $this->tableName,
                'IndexName' => self::GSI_STATUS,
                'KeyConditionExpression' => 'gsi3pk = :status',
                'FilterExpression' => 'side = :side AND attribute_not_exists(related_trade_id)',
                'ExpressionAttributeValues' => [
                    ':status' => ['S' => 'STATUS#filled'],
                    ':side' => ['S' => 'BUY'],
                ],
            ];

            if ($symbol !== null) {
                $params['FilterExpression'] .= ' AND symbol = :symbol';
                $params['ExpressionAttributeValues'][':symbol'] = ['S' => $symbol];
            }

            $result = $this->dynamoDb->query($params);

            return $this->mapResultsToCollection($result['Items'] ?? []);
        } catch (\Exception $e) {
            Log::error('Failed to get open positions', [
                'error' => $e->getMessage(),
                'symbol' => $symbol,
            ]);

            throw $e;
        }
    }

    /**
     * Compte le nombre de trades pour une date.
     */
    public function countByDate(Carbon $date): int
    {
        try {
            $result = $this->dynamoDb->query([
                'TableName' => $this->tableName,
                'IndexName' => self::GSI_DATE,
                'KeyConditionExpression' => 'gsi2pk = :date',
                'ExpressionAttributeValues' => [
                    ':date' => ['S' => 'DATE#'.$date->format('Y-m-d')],
                ],
                'Select' => 'COUNT',
            ]);

            return $result['Count'] ?? 0;
        } catch (\Exception $e) {
            Log::error('Failed to count trades by date', [
                'error' => $e->getMessage(),
                'date' => $date->format('Y-m-d'),
            ]);

            throw $e;
        }
    }

    /**
     * Calcule la somme des P&L pour une date.
     */
    public function sumPnlByDate(Carbon $date): float
    {
        $trades = $this->findByDate($date, 1000);

        return $trades->sum('pnl') ?? 0.0;
    }

    /**
     * Récupère les statistiques pour une période.
     */
    public function getStatsByPeriod(Carbon $from, Carbon $to): TradeStatsDTO
    {
        $trades = $this->findByDateRange($from, $to);

        return TradeStatsDTO::fromTrades(
            $trades->map(fn (Trade $trade) => $trade->toArray())->toArray()
        );
    }

    /**
     * Convertit les résultats DynamoDB en Collection de Trade.
     */
    private function mapResultsToCollection(array $items): Collection
    {
        return collect($items)->map(fn (array $item) => Trade::fromDynamoDB($item));
    }
}
