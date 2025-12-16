<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\TradeStatsDTO;
use App\Models\Trade;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface TradeRepositoryInterface
{
    /**
     * Crée un nouveau trade.
     */
    public function create(Trade $trade): Trade;

    /**
     * Trouve un trade par son ID.
     */
    public function findById(string $id): ?Trade;

    /**
     * Met à jour un trade existant.
     */
    public function update(Trade $trade): Trade;

    /**
     * Récupère les trades d'une date spécifique.
     */
    public function findByDate(Carbon $date, int $limit = 50): Collection;

    /**
     * Récupère les trades d'une période.
     */
    public function findByDateRange(Carbon $from, Carbon $to): Collection;

    /**
     * Récupère les trades d'un symbole.
     */
    public function findBySymbol(string $symbol, int $limit = 50): Collection;

    /**
     * Récupère les trades par statut.
     */
    public function findByStatus(string $status): Collection;

    /**
     * Récupère les positions ouvertes (achats sans vente associée).
     *
     * @param  string|null  $symbol  Filtrer par symbole (optionnel)
     */
    public function getOpenPositions(?string $symbol = null): Collection;

    /**
     * Compte le nombre de trades pour une date.
     */
    public function countByDate(Carbon $date): int;

    /**
     * Calcule la somme des P&L pour une date.
     */
    public function sumPnlByDate(Carbon $date): float;

    /**
     * Récupère les statistiques pour une période.
     */
    public function getStatsByPeriod(Carbon $from, Carbon $to): TradeStatsDTO;
}
