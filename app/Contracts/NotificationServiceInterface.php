<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\DailyReportDTO;
use App\Models\Trade;
use Throwable;

/**
 * Interface pour le service de notifications.
 *
 * Gère l'envoi de notifications via différents canaux (SNS, Telegram).
 */
interface NotificationServiceInterface
{
    /**
     * Notifie l'exécution d'un trade.
     */
    public function notifyTradeExecuted(Trade $trade): void;

    /**
     * Notifie une erreur.
     *
     * @param  array<string, mixed>  $context
     */
    public function notifyError(string $type, string $message, array $context = []): void;

    /**
     * Notifie une erreur critique.
     */
    public function notifyCriticalError(Throwable $exception): void;

    /**
     * Notifie un solde bas.
     */
    public function notifyLowBalance(string $asset, float $balance, float $threshold): void;

    /**
     * Notifie une alerte de prix.
     */
    public function notifyPriceAlert(string $symbol, float $price, string $condition): void;

    /**
     * Envoie le rapport quotidien.
     */
    public function sendDailyReport(DailyReportDTO $report): void;
}
