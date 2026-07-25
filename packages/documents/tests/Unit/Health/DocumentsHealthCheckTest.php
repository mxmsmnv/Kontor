<?php

declare(strict_types=1);

namespace Kontor\Documents\Tests\Unit\Health;

use Kontor\Documents\Health\DocumentsHealthCheck;
use PHPUnit\Framework\TestCase;

final class DocumentsHealthCheckTest extends TestCase
{
    public function test_ok_when_the_full_render_pipeline_round_trips(): void
    {
        $result = (new DocumentsHealthCheck())->run();

        $this->assertSame('ok', $result->status);
    }
}
