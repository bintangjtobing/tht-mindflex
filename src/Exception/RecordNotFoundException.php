<?php

declare(strict_types=1);

namespace Mindflex\Exception;

use RuntimeException;

final class RecordNotFoundException extends RuntimeException
{
    public static function for(string $recordType, int $id): self
    {
        return new self(sprintf('%s dengan id %d tidak ditemukan.', $recordType, $id));
    }
}
