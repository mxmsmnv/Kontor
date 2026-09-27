<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\StoredFile;

interface StorageInterface
{
    public function put(string $path, mixed $contents, array $options = []): StoredFile;

    public function read(string $path);

    public function delete(string $path): void;

    public function exists(string $path): bool;

    public function temporaryUrl(string $path, \DateTimeImmutable $expiresAt): string;
}
