<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\ReportRepositoryInterface;
use App\Models\Report;
use Aws\DynamoDb\DynamoDbClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class DynamoDbReportRepository implements ReportRepositoryInterface
{
    private const TABLE_NAME_PREFIX = 'trading-bot';

    private string $tableName;

    public function __construct(
        private readonly DynamoDbClient $dynamoDb,
    ) {
        $environment = config('app.env', 'dev');
        $this->tableName = self::TABLE_NAME_PREFIX."-{$environment}-reports";
    }

    /**
     * Crée un nouveau rapport.
     */
    public function create(Report $report): Report
    {
        try {
            $this->dynamoDb->putItem([
                'TableName' => $this->tableName,
                'Item' => $report->toDynamoDB(),
            ]);

            Log::info('Report created in DynamoDB', [
                'date' => $report->date->format('Y-m-d'),
                'trades_count' => $report->tradesCount,
                'pnl' => $report->pnlAbsolute,
            ]);

            return $report;
        } catch (\Exception $e) {
            Log::error('Failed to create report in DynamoDB', [
                'error' => $e->getMessage(),
                'date' => $report->date->format('Y-m-d'),
            ]);

            throw $e;
        }
    }

    /**
     * Trouve un rapport par sa date.
     */
    public function findByDate(Carbon $date): ?Report
    {
        try {
            $result = $this->dynamoDb->getItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'pk' => ['S' => 'REPORT#'.$date->format('Y-m-d')],
                    'sk' => ['S' => 'DAILY'],
                ],
            ]);

            if (! isset($result['Item'])) {
                return null;
            }

            return Report::fromDynamoDB($result['Item']);
        } catch (\Exception $e) {
            Log::error('Failed to find report by date', [
                'error' => $e->getMessage(),
                'date' => $date->format('Y-m-d'),
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les rapports d'une période.
     */
    public function findByDateRange(Carbon $from, Carbon $to): Collection
    {
        try {
            $reports = collect();

            // Itérer sur chaque jour de la période
            $current = $from->copy();
            while ($current->lte($to)) {
                $report = $this->findByDate($current);
                if ($report !== null) {
                    $reports->push($report);
                }
                $current->addDay();
            }

            return $reports;
        } catch (\Exception $e) {
            Log::error('Failed to find reports by date range', [
                'error' => $e->getMessage(),
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ]);

            throw $e;
        }
    }

    /**
     * Récupère le dernier rapport généré.
     */
    public function getLatest(): ?Report
    {
        try {
            // Chercher le rapport d'hier, puis aujourd'hui
            $yesterday = Carbon::yesterday();
            $report = $this->findByDate($yesterday);

            if ($report !== null) {
                return $report;
            }

            // Si pas de rapport hier, chercher aujourd'hui
            return $this->findByDate(Carbon::today());
        } catch (\Exception $e) {
            Log::error('Failed to get latest report', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Récupère les rapports du mois.
     */
    public function getByMonth(int $year, int $month): Collection
    {
        try {
            $from = Carbon::create($year, $month, 1)->startOfMonth();
            $to = $from->copy()->endOfMonth();

            return $this->findByDateRange($from, $to);
        } catch (\Exception $e) {
            Log::error('Failed to get reports by month', [
                'error' => $e->getMessage(),
                'year' => $year,
                'month' => $month,
            ]);

            throw $e;
        }
    }

    /**
     * Vérifie si un rapport existe pour une date.
     */
    public function existsForDate(Carbon $date): bool
    {
        return $this->findByDate($date) !== null;
    }
}
