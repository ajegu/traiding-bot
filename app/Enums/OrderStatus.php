<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'NEW';
    case PartiallyFilled = 'PARTIALLY_FILLED';
    case Filled = 'FILLED';
    case Canceled = 'CANCELED';
    case PendingCancel = 'PENDING_CANCEL';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Error = 'ERROR';

    /**
     * Retourne le libellé en français.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'En attente',
            self::PartiallyFilled => 'Partiellement exécuté',
            self::Filled => 'Exécuté',
            self::Canceled => 'Annulé',
            self::PendingCancel => 'Annulation en cours',
            self::Rejected => 'Rejeté',
            self::Expired => 'Expiré',
            self::Error => 'Erreur',
        };
    }

    /**
     * Indique si le statut est final (l'ordre ne changera plus).
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Filled,
            self::Canceled,
            self::Rejected,
            self::Expired,
            self::Error,
        ], true);
    }

    /**
     * Indique si l'ordre a été exécuté (au moins partiellement).
     */
    public function isExecuted(): bool
    {
        return in_array($this, [
            self::PartiallyFilled,
            self::Filled,
        ], true);
    }

    /**
     * Indique si l'ordre est en cours de traitement.
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::New,
            self::PartiallyFilled,
            self::PendingCancel,
        ], true);
    }

    /**
     * Retourne la couleur CSS associée au statut.
     */
    public function color(): string
    {
        return match ($this) {
            self::Filled => 'green',
            self::PartiallyFilled => 'blue',
            self::New, self::PendingCancel => 'yellow',
            self::Canceled, self::Expired => 'gray',
            self::Rejected, self::Error => 'red',
        };
    }

    /**
     * Créer depuis une réponse Binance (lowercase).
     */
    public static function fromBinance(string $status): self
    {
        return self::from(strtoupper($status));
    }
}
