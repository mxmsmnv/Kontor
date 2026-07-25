<?php

declare(strict_types=1);

namespace Kontor\Automation\Tests\Unit\Application;

use Kontor\Automation\Application\ConditionEvaluator;
use Kontor\Automation\Domain\RuleCondition;
use PHPUnit\Framework\TestCase;

final class ConditionEvaluatorTest extends TestCase
{
    private function condition(string $field, string $operator, ?string $value): RuleCondition
    {
        return RuleCondition::create('org_01', 'rule_01', $field, $operator, $value);
    }

    public function test_equals_matches_a_dot_path_field(): void
    {
        $evaluator = new ConditionEvaluator();
        $data = ['movementType' => 'receive'];

        $this->assertTrue($evaluator->evaluate([$this->condition('movementType', 'equals', 'receive')], $data));
        $this->assertFalse($evaluator->evaluate([$this->condition('movementType', 'equals', 'transfer')], $data));
    }

    public function test_resolves_nested_dot_paths(): void
    {
        $evaluator = new ConditionEvaluator();
        $data = ['order' => ['status' => 'confirmed']];

        $this->assertTrue($evaluator->evaluate([$this->condition('order.status', 'equals', 'confirmed')], $data));
    }

    public function test_not_equals(): void
    {
        $evaluator = new ConditionEvaluator();
        $data = ['status' => 'open'];

        $this->assertTrue($evaluator->evaluate([$this->condition('status', 'not_equals', 'closed')], $data));
        $this->assertFalse($evaluator->evaluate([$this->condition('status', 'not_equals', 'open')], $data));
    }

    public function test_greater_than_and_less_than_are_numeric(): void
    {
        $evaluator = new ConditionEvaluator();
        $data = ['quantity' => 10];

        $this->assertTrue($evaluator->evaluate([$this->condition('quantity', 'greater_than', '5')], $data));
        $this->assertFalse($evaluator->evaluate([$this->condition('quantity', 'greater_than', '15')], $data));
        $this->assertTrue($evaluator->evaluate([$this->condition('quantity', 'less_than', '15')], $data));
    }

    public function test_contains_is_a_substring_match(): void
    {
        $evaluator = new ConditionEvaluator();
        $data = ['description' => 'Widget delivered late'];

        $this->assertTrue($evaluator->evaluate([$this->condition('description', 'contains', 'delivered')], $data));
        $this->assertFalse($evaluator->evaluate([$this->condition('description', 'contains', 'refund')], $data));
    }

    public function test_a_missing_field_never_matches(): void
    {
        $evaluator = new ConditionEvaluator();

        $this->assertFalse($evaluator->evaluate([$this->condition('missing', 'equals', 'anything')], []));
    }

    public function test_multiple_conditions_use_and_semantics(): void
    {
        $evaluator = new ConditionEvaluator();
        $data = ['movementType' => 'receive', 'quantity' => 10];

        $conditions = [
            $this->condition('movementType', 'equals', 'receive'),
            $this->condition('quantity', 'greater_than', '5'),
        ];
        $this->assertTrue($evaluator->evaluate($conditions, $data));

        $conditions[] = $this->condition('quantity', 'greater_than', '50');
        $this->assertFalse($evaluator->evaluate($conditions, $data));
    }

    public function test_no_conditions_always_matches(): void
    {
        $this->assertTrue((new ConditionEvaluator())->evaluate([], ['anything' => 'here']));
    }
}
