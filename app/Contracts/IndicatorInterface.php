<?php

declare(strict_types=1);

namespace App\Contracts;

interface IndicatorInterface
{
    /**
     * Calcule l'indicateur sur un tableau de prix.
     *
     * @param  array<float>  $prices  Tableau des prix (généralement close prices)
     * @return float Valeur de l'indicateur
     */
    public function calculate(array $prices): float;

    /**
     * Retourne le nom de l'indicateur.
     */
    public function getName(): string;

    /**
     * Retourne la période requise pour le calcul.
     */
    public function getRequiredPeriod(): int;
}
