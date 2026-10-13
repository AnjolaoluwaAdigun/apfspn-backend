<?php

namespace App\Exceptions;

use LogicException;
use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(string $message, private readonly int $status = 422, private readonly array $payload = [])
    {
        if (! isset($payload['code'])) {
            throw new LogicException('ApiException payload must include a code.');
        }

        parent::__construct($message);
    }

    public static function blocked(string $message, array $counts): self
    {
        return new self($message, 422, ['code' => 'blocked', 'counts' => $counts]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function payload(): array
    {
        return $this->payload;
    }
}
