<?php

declare(strict_types=1);

namespace Kontor\Entities\Tests\Unit\Domain;

use Kontor\Entities\Domain\EntityField;
use PHPUnit\Framework\TestCase;

final class EntityFieldTest extends TestCase
{
    public function test_create_accepts_every_supported_type(): void
    {
        foreach (EntityField::TYPES as $type) {
            $field = EntityField::create('org_01', 'def_01', 'a_field', 'A Field', $type);
            $this->assertSame($type, $field->fieldType);
        }
    }

    public function test_create_rejects_an_unsupported_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EntityField::create('org_01', 'def_01', 'a_field', 'A Field', 'array');
    }
}
