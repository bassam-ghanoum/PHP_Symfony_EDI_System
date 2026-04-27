<?php

declare(strict_types=1);

namespace App\Exception;

use Exception;

/**
 * Exception thrown when there are issues communicating with the database.
 * This includes query failures, persistence errors, and other database-related operations.
 */
final class DatabaseException extends Exception
{
    public static function fromThrowable(\Throwable $previous, string $operation = 'database operation'): self
    {
        return new self(
            message: sprintf('Database error during %s: %s', $operation, $previous->getMessage()),
            code: 0,
            previous: $previous
        );
    }
}
