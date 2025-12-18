<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\DailyReportDTO;
use Carbon\Carbon;

/**
 * Interface pour le service de génération de rapports.
 *
 * Gère le calcul des P&L et la génération de rapports quotidiens.
 */
interface ReportServiceInterface
{
    /**
     * Génère le rapport quotidien pour une date donnée.
     *
     * @param  Carbon|null  $date  Date du rapport (défaut: hier)
     */
    public function generateDailyReport(?Carbon $date = null): DailyReportDTO;

    /**
     * Calcule le P&L total pour une période donnée.
     *
     * @return array{pnl: float, pnl_percent: float, winning_trades: int, losing_trades: int}
     */
    public function calculatePnl(Carbon $from, Carbon $to): array;

    /**
     * Calcule le P&L pour un trade de vente.
     *
     * Associe le trade de vente avec le trade d'achat correspondant
     * et calcule le profit/perte en tenant compte des frais.
     */
    public function calculateTradePnl(string $tradeId): ?float;

    /**
     * Récupère la valeur totale du portefeuille en USDT.
     *
     * @return array{total_usdt: float, balances: array<string, float>}
     */
    public function getPortfolioValue(): array;

    /**
     * Archive un rapport quotidien dans DynamoDB.
     */
    public function archiveReport(DailyReportDTO $report): bool;
}
