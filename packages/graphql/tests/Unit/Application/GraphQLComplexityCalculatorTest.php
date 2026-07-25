<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Unit\Application;

use Kontor\GraphQL\Application\GraphQLComplexityCalculator;
use Kontor\GraphQL\DTO\GraphQLDocument;
use Kontor\GraphQL\DTO\GraphQLSelection;
use PHPUnit\Framework\TestCase;

final class GraphQLComplexityCalculatorTest extends TestCase
{
    public function test_a_single_record_selection_has_a_multiplier_of_one(): void
    {
        $calculator = new GraphQLComplexityCalculator();
        $document = new GraphQLDocument([new GraphQLSelection('organizations', ['uid' => 'org_1'], ['uid', 'name', 'status'])]);

        $this->assertSame(3, $calculator->complexityOf($document));
    }

    public function test_a_list_selection_is_multiplied_by_page_size(): void
    {
        $calculator = new GraphQLComplexityCalculator();
        $document = new GraphQLDocument([new GraphQLSelection('organizations', ['pageSize' => 20], ['uid', 'name'])]);

        $this->assertSame(40, $calculator->complexityOf($document));
    }

    public function test_a_list_selection_without_page_size_defaults_to_fifty(): void
    {
        $calculator = new GraphQLComplexityCalculator();
        $document = new GraphQLDocument([new GraphQLSelection('organizations', [], ['uid'])]);

        $this->assertSame(50, $calculator->complexityOf($document));
    }

    public function test_multiple_selections_are_summed(): void
    {
        $calculator = new GraphQLComplexityCalculator();
        $document = new GraphQLDocument([
            new GraphQLSelection('organizations', ['uid' => 'org_1'], ['uid']),
            new GraphQLSelection('widgets', ['pageSize' => 10], ['uid', 'name']),
        ]);

        $this->assertSame(1 + 20, $calculator->complexityOf($document));
    }

    public function test_exceeds_limit(): void
    {
        $calculator = new GraphQLComplexityCalculator(maxComplexity: 100);
        $cheap = new GraphQLDocument([new GraphQLSelection('organizations', ['uid' => 'org_1'], ['uid'])]);
        $expensive = new GraphQLDocument([new GraphQLSelection('organizations', ['pageSize' => 200], ['uid', 'name', 'status'])]);

        $this->assertFalse($calculator->exceedsLimit($cheap));
        $this->assertTrue($calculator->exceedsLimit($expensive));
    }
}
