<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Database;

use PHPUnit\Framework\TestCase;

final class AggregatePortabilityTest extends TestCase
{
    public function testCatalogSummaryDoesNotSumBooleanExpressions(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 4) . '/packages/catalog/src/Infrastructure/Persistence/CatalogItemRepository.php'
        );

        self::assertDoesNotMatchRegularExpression(
            '/SUM\s*\(\s*(?!CASE\b)[^)]*(?:\s=\s|\sIS\s+(?:NOT\s+)?NULL)/i',
            $source
        );
        self::assertSame(6, substr_count($source, 'SUM(CASE WHEN'));
    }
}
