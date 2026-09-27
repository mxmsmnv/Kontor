<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Application;

use Kontor\API\Application\ApiFieldProjector;
use PHPUnit\Framework\TestCase;

final class ApiFieldProjectorTest extends TestCase
{
    public function test_null_fields_returns_the_row_unchanged(): void
    {
        $projector = new ApiFieldProjector();
        $row = ['uid' => '1', 'name' => 'Acme', 'status' => 'active'];

        $this->assertSame($row, $projector->project($row, null));
    }

    public function test_projects_only_the_requested_fields(): void
    {
        $projector = new ApiFieldProjector();
        $row = ['uid' => '1', 'name' => 'Acme', 'status' => 'active'];

        $this->assertSame(['uid' => '1', 'name' => 'Acme'], $projector->project($row, ['uid', 'name']));
    }

    public function test_requesting_a_field_that_does_not_exist_is_silently_dropped(): void
    {
        $projector = new ApiFieldProjector();
        $row = ['uid' => '1'];

        $this->assertSame(['uid' => '1'], $projector->project($row, ['uid', 'nonexistent']));
    }
}
