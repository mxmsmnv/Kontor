<?php

declare(strict_types=1);

namespace Kontor\Workflow\Tests\Unit\Domain;

use Kontor\Workflow\Domain\WorkflowDefinition;
use PHPUnit\Framework\TestCase;

final class WorkflowDefinitionTest extends TestCase
{
    public function test_create_rejects_an_initial_state_not_in_the_declared_states(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WorkflowDefinition::create('org_01', 'simple_approval', 'expense', 'Simple approval', 'missing', ['draft', 'approved']);
    }

    public function test_has_state(): void
    {
        $definition = WorkflowDefinition::create('org_01', 'simple_approval', 'expense', 'Simple approval', 'draft', ['draft', 'submitted', 'approved', 'rejected']);

        $this->assertTrue($definition->hasState('submitted'));
        $this->assertFalse($definition->hasState('archived'));
    }
}
