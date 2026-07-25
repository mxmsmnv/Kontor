<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Application;

use Kontor\API\Application\ApiResponseFactory;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use PHPUnit\Framework\TestCase;

final class ApiResponseFactoryTest extends TestCase
{
    public function test_success_envelope_shape(): void
    {
        $factory = new ApiResponseFactory();

        $envelope = $factory->success(['uid' => '1'], 'req_123');

        $this->assertSame(['uid' => '1'], $envelope['data']);
        $this->assertSame('req_123', $envelope['meta']['requestId']);
        $this->assertSame('v1', $envelope['meta']['version']);
    }

    public function test_error_envelope_shape(): void
    {
        $factory = new ApiResponseFactory();

        $envelope = $factory->error('validation_failed', 'The request contains invalid data.', ['field' => 'name'], 'req_123');

        $this->assertSame('validation_failed', $envelope['error']['code']);
        $this->assertSame('The request contains invalid data.', $envelope['error']['message']);
        $this->assertSame(['field' => 'name'], $envelope['error']['details']);
        $this->assertSame('req_123', $envelope['error']['requestId']);
    }

    public function test_collection_computes_total_pages(): void
    {
        $factory = new ApiResponseFactory();
        $result = new ApiCollectionResult(rows: [['uid' => '1'], ['uid' => '2']], total: 101);
        $query = new ApiQuery(page: 2, pageSize: 50);

        $envelope = $factory->collection($result, $query, 'req_123');

        $this->assertSame(2, $envelope['meta']['page']);
        $this->assertSame(50, $envelope['meta']['pageSize']);
        $this->assertSame(101, $envelope['meta']['total']);
        $this->assertSame(3, $envelope['meta']['totalPages']);
    }

    public function test_request_id_has_the_req_prefix(): void
    {
        $factory = new ApiResponseFactory();

        $this->assertStringStartsWith('req_', $factory->newRequestId());
    }
}
