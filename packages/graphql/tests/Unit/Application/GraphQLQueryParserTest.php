<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Unit\Application;

use Kontor\GraphQL\Application\GraphQLQueryParser;
use Kontor\GraphQL\Application\GraphQLSyntaxException;
use PHPUnit\Framework\TestCase;

final class GraphQLQueryParserTest extends TestCase
{
    private GraphQLQueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new GraphQLQueryParser();
    }

    public function test_a_single_record_selection_by_uid(): void
    {
        $document = $this->parser->parse('{ organizations(uid: "org_123") { uid name } }');

        $this->assertCount(1, $document->selections);
        $selection = $document->selections[0];
        $this->assertSame('organizations', $selection->resourceKey);
        $this->assertSame(['uid' => 'org_123'], $selection->arguments);
        $this->assertSame(['uid', 'name'], $selection->fields);
    }

    public function test_a_list_selection_with_pagination_arguments(): void
    {
        $document = $this->parser->parse('{ organizations(page: 2, pageSize: 10) { uid } }');

        $this->assertSame(['page' => 2, 'pageSize' => 10], $document->selections[0]->arguments);
    }

    public function test_a_selection_with_no_arguments(): void
    {
        $document = $this->parser->parse('{ organizations { uid name } }');

        $this->assertSame([], $document->selections[0]->arguments);
    }

    public function test_multiple_root_selections(): void
    {
        $document = $this->parser->parse('{ organizations { uid } widgets { uid } }');

        $this->assertCount(2, $document->selections);
        $this->assertSame('organizations', $document->selections[0]->resourceKey);
        $this->assertSame('widgets', $document->selections[1]->resourceKey);
    }

    public function test_a_leading_query_keyword_is_accepted(): void
    {
        $document = $this->parser->parse('query { organizations { uid } }');

        $this->assertCount(1, $document->selections);
    }

    public function test_boolean_argument_values(): void
    {
        $document = $this->parser->parse('{ organizations(archived: false) { uid } }');

        $this->assertSame(['archived' => false], $document->selections[0]->arguments);
    }

    public function test_empty_document_is_a_syntax_error(): void
    {
        $this->expectException(GraphQLSyntaxException::class);

        $this->parser->parse('');
    }

    public function test_a_selection_with_no_fields_is_a_syntax_error(): void
    {
        $this->expectException(GraphQLSyntaxException::class);

        $this->parser->parse('{ organizations { } }');
    }

    public function test_an_unterminated_string_is_a_syntax_error(): void
    {
        $this->expectException(GraphQLSyntaxException::class);

        $this->parser->parse('{ organizations(uid: "org_123) { uid } }');
    }

    public function test_a_nested_object_argument_is_out_of_scope_and_rejected(): void
    {
        $this->expectException(GraphQLSyntaxException::class);

        $this->parser->parse('{ organizations(filter: { status: "active" }) { uid } }');
    }

    public function test_trailing_input_after_the_document_is_rejected(): void
    {
        $this->expectException(GraphQLSyntaxException::class);

        $this->parser->parse('{ organizations { uid } } garbage');
    }

    public function test_an_unexpected_character_is_rejected(): void
    {
        $this->expectException(GraphQLSyntaxException::class);

        $this->parser->parse('{ organizations(uid: @bad) { uid } }');
    }
}
