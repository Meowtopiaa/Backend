<?php

namespace App\Modules\IncidentReport\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Kegagalan aturan domain insiden yang dipetakan ke format error PRD 8.1.
 */
class IncidentDomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 409,
        public readonly string $errorCode = 'CONFLICT',
        public readonly mixed $errors = null,
    ) {
        parent::__construct($message);
    }

    public static function conflict(string $message, mixed $errors = null): self
    {
        return new self($message, 409, 'CONFLICT', $errors);
    }

    public static function forbidden(string $message = 'Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.'): self
    {
        return new self($message, 403, 'FORBIDDEN');
    }

    public static function notFound(string $message = 'Data tidak ditemukan atau tidak dapat diakses.'): self
    {
        return new self($message, 404, 'NOT_FOUND');
    }

    public static function validation(string $message, array $errors = []): self
    {
        return new self($message, 422, 'VALIDATION_ERROR', $errors ?: null);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->getMessage(), $this->status, $this->errorCode, $this->errors);
    }
}
