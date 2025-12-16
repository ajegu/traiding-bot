<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class BinanceApiException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        public readonly ?array $context = null,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Retourne le contexte de l'exception.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context ?? [];
    }

    /**
     * Vérifie si l'erreur est retryable.
     */
    public function isRetryable(): bool
    {
        return in_array($this->code, [
            -1000, // Unknown error
            -1001, // Disconnected
            -1003, // Too many requests
            -1007, // Timeout
        ], true);
    }

    /**
     * Crée une exception depuis une réponse API Binance.
     */
    public static function fromApiResponse(array $response, int $httpCode = 0): self
    {
        $message = $response['msg'] ?? 'Unknown Binance API error';
        $code = $response['code'] ?? $httpCode;

        return new self(
            message: $message,
            code: $code,
            context: [
                'http_code' => $httpCode,
                'binance_code' => $response['code'] ?? null,
                'response' => $response,
            ]
        );
    }
}
