<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

final class ApiHttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function json(int $status, array $data): self
    {
        return new self($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_THROW_ON_ERROR));
    }

    public static function noContent(int $status): self
    {
        return new self($status, [], '');
    }
}
