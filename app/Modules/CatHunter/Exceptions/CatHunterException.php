<?php

namespace App\Modules\CatHunter\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis CatHunter (cooldown, syarat rename, dll.).
 * Dipetakan ke ApiResponse::error oleh CatHunterController.
 */
class CatHunterException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly mixed $errors = null,
    ) {
        parent::__construct($message);
    }
}
