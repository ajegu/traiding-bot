<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface ReportRepositoryInterface
{
    /**
     * Crée un nouveau rapport.
     */
    public function create(Report $report): Report;

    /**
     * Trouve un rapport par sa date.
     */
    public function findByDate(Carbon $date): ?Report;

    /**
     * Récupère les rapports d'une période.
     */
    public function findByDateRange(Carbon $from, Carbon $to): Collection;

    /**
     * Récupère le dernier rapport généré.
     */
    public function getLatest(): ?Report;

    /**
     * Récupère les rapports du mois.
     */
    public function getByMonth(int $year, int $month): Collection;

    /**
     * Vérifie si un rapport existe pour une date.
     */
    public function existsForDate(Carbon $date): bool;
}
