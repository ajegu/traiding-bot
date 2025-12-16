<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(
        string $message = 'Insufficient balance',
        public readonly ?string $asset = null,
        public readonly ?float $required = null,
        public readonly ?float $available = null,
    ) {
        parent::__construct($message);
    }

    /**
     * Retourne le contexte de l'exception.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'asset' => $this->asset,
            'required' => $this->required,
            'available' => $this->available,
        ];
    }
}
