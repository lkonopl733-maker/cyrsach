<?php

namespace App\Exceptions;

use RuntimeException;

class TheMealDbException extends RuntimeException
{
    public static function requestFailed(string $endpoint, int $status): self
    {
        return new self("Помилка запиту до TheMealDB API ({$endpoint}). HTTP статус: {$status}");
    }

    public static function invalidPayload(string $endpoint): self
    {
        return new self("Отримано некоректну структуру JSON від TheMealDB API ({$endpoint})");
    }
}