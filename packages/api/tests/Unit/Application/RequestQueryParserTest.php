<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Application;

use Kontor\API\Application\RequestQueryParser;
use PHPUnit\Framework\TestCase;

final class RequestQueryParserTest extends TestCase
{
    private RequestQueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RequestQueryParser();
    }

    public function test_defaults_when_nothing_is_provided(): void
    {
        $query = $this->parser->parse([], 'invoices');

        $this->assertSame(1, $query->page);
        $this->assertSame(50, $query->pageSize);
        $this->assertSame([], $query->filters);
        $this->assertSame([], $query->sort);
        $this->assertNull($query->fields);
        $this->assertSame([], $query->include);
    }

    public function test_pagination(): void
    {
        $query = $this->parser->parse(['page' => ['number' => '3', 'size' => '25']], 'invoices');

        $this->assertSame(3, $query->page);
        $this->assertSame(25, $query->pageSize);
    }

    public function test_page_size_is_capped(): void
    {
        $query = $this->parser->parse(['page' => ['size' => '999999']], 'invoices');

        $this->assertSame(200, $query->pageSize);
    }

    public function test_page_number_cannot_go_below_one(): void
    {
        $query = $this->parser->parse(['page' => ['number' => '0']], 'invoices');

        $this->assertSame(1, $query->page);
    }

    public function test_filters(): void
    {
        $query = $this->parser->parse(['filter' => ['status' => 'paid', 'customer_uid' => 'cmp_01']], 'invoices');

        $this->assertSame(['status' => 'paid', 'customer_uid' => 'cmp_01'], $query->filters);
    }

    public function test_sort_with_descending_prefix(): void
    {
        $query = $this->parser->parse(['sort' => '-created_at,number'], 'invoices');

        $this->assertSame([
            ['field' => 'created_at', 'direction' => 'desc'],
            ['field' => 'number', 'direction' => 'asc'],
        ], $query->sort);
    }

    public function test_sparse_fields_are_scoped_to_the_resource_key(): void
    {
        $query = $this->parser->parse(['fields' => ['invoices' => 'uid,number,status', 'customers' => 'uid,name']], 'invoices');

        $this->assertSame(['uid', 'number', 'status'], $query->fields);
    }

    public function test_sparse_fields_missing_for_this_resource_is_null(): void
    {
        $query = $this->parser->parse(['fields' => ['customers' => 'uid,name']], 'invoices');

        $this->assertNull($query->fields);
    }

    public function test_include(): void
    {
        $query = $this->parser->parse(['include' => 'customer,lines,payments'], 'invoices');

        $this->assertSame(['customer', 'lines', 'payments'], $query->include);
    }
}
