<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\BotConfig;

interface BotConfigRepositoryInterface
{
    /**
     * Récupère la configuration du bot.
     */
    public function get(): ?BotConfig;

    /**
     * Sauvegarde la configuration du bot.
     */
    public function save(BotConfig $config): BotConfig;

    /**
     * Active ou désactive le bot.
     */
    public function setEnabled(bool $enabled): void;

    /**
     * Vérifie si le bot est activé.
     */
    public function isEnabled(): bool;

    /**
     * Définit la stratégie active.
     */
    public function setStrategy(string $strategy): void;

    /**
     * Récupère la stratégie active.
     */
    public function getStrategy(): string;

    /**
     * Met à jour le timestamp de la dernière exécution.
     */
    public function updateLastExecution(): void;

    /**
     * Récupère le timestamp de la dernière exécution.
     */
    public function getLastExecution(): ?string;
}
