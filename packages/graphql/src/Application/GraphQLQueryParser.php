<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Application;

use Kontor\GraphQL\DTO\GraphQLDocument;
use Kontor\GraphQL\DTO\GraphQLSelection;

/**
 * A hand-rolled parser for a deliberately small subset of GraphQL query
 * syntax — no third-party GraphQL library, the same "avoid a heavy
 * dependency for a narrow need" call `kontor/documents` already made for
 * its own template engine and XLSX reader/writer.
 *
 * Supported: an optional leading `query` keyword, one or more root-level
 * `resourceKey(arguments) { field field }` selections, and scalar
 * arguments (`uid`: string, `page`/`pageSize`: int, or a bare
 * `true`/`false`). Arbitrary `filter: { ... }`-style nested object
 * arguments are deliberately out of scope for this substage — that would
 * require a full value grammar (nested objects/lists/variables) this
 * component's "component types"/"complexity limits" milestones don't
 * need to demonstrate; a query using one is rejected with a clear syntax
 * error rather than silently ignored.
 */
final class GraphQLQueryParser
{
    /**
     * @throws GraphQLSyntaxException
     */
    public function parse(string $query): GraphQLDocument
    {
        $tokens = $this->tokenize($query);
        $position = 0;

        if (($tokens[$position]['type'] ?? null) === 'name' && $tokens[$position]['value'] === 'query') {
            $position++;
        }

        $selections = $this->parseSelectionSet($tokens, $position);

        if ($position !== count($tokens)) {
            throw new GraphQLSyntaxException('Unexpected trailing input after the query document.');
        }

        if ($selections === []) {
            throw new GraphQLSyntaxException('A query document must select at least one resource.');
        }

        return new GraphQLDocument($selections);
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $query): array
    {
        $tokens = [];
        $length = strlen($query);
        $i = 0;

        while ($i < $length) {
            $char = $query[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if (in_array($char, ['{', '}', '(', ')', ':', ','], true)) {
                $tokens[] = ['type' => $char, 'value' => $char];
                $i++;

                continue;
            }

            if ($char === '"') {
                [$value, $i] = $this->tokenizeString($query, $i, $length);
                $tokens[] = ['type' => 'string', 'value' => $value];

                continue;
            }

            if (ctype_digit($char) || ($char === '-' && $i + 1 < $length && ctype_digit($query[$i + 1]))) {
                $end = $i + 1;

                while ($end < $length && ctype_digit($query[$end])) {
                    $end++;
                }

                $tokens[] = ['type' => 'int', 'value' => substr($query, $i, $end - $i)];
                $i = $end;

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $end = $i + 1;

                while ($end < $length && (ctype_alnum($query[$end]) || $query[$end] === '_')) {
                    $end++;
                }

                $tokens[] = ['type' => 'name', 'value' => substr($query, $i, $end - $i)];
                $i = $end;

                continue;
            }

            throw new GraphQLSyntaxException("Unexpected character \"{$char}\" in query.");
        }

        return $tokens;
    }

    /**
     * @return array{0: string, 1: int} the decoded string value and the index just past the closing quote
     */
    private function tokenizeString(string $query, int $start, int $length): array
    {
        $end = $start + 1;
        $value = '';

        while ($end < $length && $query[$end] !== '"') {
            if ($query[$end] === '\\' && $end + 1 < $length) {
                $value .= $query[$end + 1];
                $end += 2;

                continue;
            }

            $value .= $query[$end];
            $end++;
        }

        if ($end >= $length) {
            throw new GraphQLSyntaxException('Unterminated string literal.');
        }

        return [$value, $end + 1];
    }

    /**
     * @param list<array{type: string, value: string}> $tokens
     * @return GraphQLSelection[]
     */
    private function parseSelectionSet(array $tokens, int &$position): array
    {
        $this->expect($tokens, $position, '{');

        $selections = [];

        while ($this->peekType($tokens, $position) !== '}') {
            $selections[] = $this->parseSelection($tokens, $position);
        }

        $this->expect($tokens, $position, '}');

        return $selections;
    }

    /**
     * @param list<array{type: string, value: string}> $tokens
     */
    private function parseSelection(array $tokens, int &$position): GraphQLSelection
    {
        $resourceKey = $this->expect($tokens, $position, 'name');

        $arguments = $this->peekType($tokens, $position) === '('
            ? $this->parseArguments($tokens, $position)
            : [];

        $this->expect($tokens, $position, '{');

        $fields = [];

        while ($this->peekType($tokens, $position) !== '}') {
            $fields[] = $this->expect($tokens, $position, 'name');
        }

        $this->expect($tokens, $position, '}');

        if ($fields === []) {
            throw new GraphQLSyntaxException("Resource \"{$resourceKey}\" must select at least one field.");
        }

        return new GraphQLSelection($resourceKey, $arguments, $fields);
    }

    /**
     * @param list<array{type: string, value: string}> $tokens
     * @return array<string, string|int|bool>
     */
    private function parseArguments(array $tokens, int &$position): array
    {
        $this->expect($tokens, $position, '(');

        $arguments = [];

        while (true) {
            $name = $this->expect($tokens, $position, 'name');
            $this->expect($tokens, $position, ':');
            $arguments[$name] = $this->parseValue($tokens, $position);

            if ($this->peekType($tokens, $position) === ',') {
                $this->expect($tokens, $position, ',');

                continue;
            }

            break;
        }

        $this->expect($tokens, $position, ')');

        return $arguments;
    }

    /**
     * @param list<array{type: string, value: string}> $tokens
     */
    private function parseValue(array $tokens, int &$position): string|int|bool
    {
        return match ($this->peekType($tokens, $position)) {
            'string' => $this->expect($tokens, $position, 'string'),
            'int' => (int) $this->expect($tokens, $position, 'int'),
            'name' => $this->parseBooleanLiteral($tokens, $position),
            default => throw new GraphQLSyntaxException('Expected a string, integer, or boolean argument value.'),
        };
    }

    /**
     * @param list<array{type: string, value: string}> $tokens
     */
    private function parseBooleanLiteral(array $tokens, int &$position): bool
    {
        $value = $this->expect($tokens, $position, 'name');

        return match ($value) {
            'true' => true,
            'false' => false,
            default => throw new GraphQLSyntaxException("Expected \"true\" or \"false\", got \"{$value}\"."),
        };
    }

    /**
     * @param list<array{type: string, value: string}> $tokens
     */
    private function peekType(array $tokens, int $position): ?string
    {
        return $tokens[$position]['type'] ?? null;
    }

    /**
     * @param list<array{type: string, value: string}> $tokens
     */
    private function expect(array $tokens, int &$position, string $expectedType): string
    {
        $token = $tokens[$position] ?? null;

        if ($token === null || $token['type'] !== $expectedType) {
            $found = $token !== null ? "\"{$token['value']}\"" : 'end of input';

            throw new GraphQLSyntaxException("Expected \"{$expectedType}\" but found {$found}.");
        }

        $position++;

        return $token['value'];
    }
}
