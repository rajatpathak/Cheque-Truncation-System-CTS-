<?php

namespace App\Exceptions;

use RuntimeException;

class SignatureException extends RuntimeException
{
    public function __construct(
        string $message = 'PKI signature operation failed.',
        private readonly string $operation = 'SIGN',
        private readonly ?string $instrumentId = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getInstrumentId(): ?string
    {
        return $this->instrumentId;
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error'          => 'SIGNATURE_ERROR',
            'message'        => $this->getMessage(),
            'operation'      => $this->operation,
            'instrument_id'  => $this->instrumentId,
        ], 500);
    }
}
