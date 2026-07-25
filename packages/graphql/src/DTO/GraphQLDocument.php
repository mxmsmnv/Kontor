<?php

declare(strict_types=1);

namespace Kontor\GraphQL\DTO;

final class GraphQLDocument
{
    /**
     * @param GraphQLSelection[] $selections
     */
    public function __construct(
        public readonly array $selections,
    ) {
    }
}
