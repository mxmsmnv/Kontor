<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

interface RepositoryInterface
{
    public function find(string $id): ?object;

    public function require(string $id): object;

    public function save(object $entity): void;

    public function archive(string $id): void;

    public function restore(string $id): void;
}
