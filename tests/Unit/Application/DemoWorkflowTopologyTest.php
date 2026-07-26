<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Demo\Application\DemoScenarioService;
use Kontor\Demo\Domain\DemoScenario;
use PHPUnit\Framework\TestCase;

final class DemoWorkflowTopologyTest extends TestCase
{
    public function test_demo_scenario_starts_at_intake_and_records_connected_entities(): void
    {
        $scenario = DemoScenario::create(
            '01H00000000000000000000000',
            'Order to cash',
            41,
        );

        $scenario->record([
            'company' => '01H00000000000000000000001',
            'deal' => '01H00000000000000000000002',
        ]);
        $scenario->record(['quotation' => '01H00000000000000000000003']);

        self::assertSame('intake', $scenario->currentState);
        self::assertSame('active', $scenario->status);
        self::assertSame('01H00000000000000000000002', $scenario->entity('deal'));
        self::assertSame('01H00000000000000000000003', $scenario->entity('quotation'));
        self::assertNull($scenario->entity('invoice'));
    }

    public function test_demo_entity_map_rejects_empty_keys_or_uids(): void
    {
        $scenario = DemoScenario::create('01H00000000000000000000000', 'Demo');

        $this->expectException(\InvalidArgumentException::class);
        $scenario->record(['invoice' => '']);
    }

    public function test_order_to_cash_topology_is_contiguous_and_has_one_approval_gate(): void
    {
        $state = 'intake';
        $approvalActions = [];

        foreach (DemoScenarioService::TRANSITIONS as $action => $transition) {
            self::assertSame($state, $transition['from'], "Transition {$action} is disconnected.");
            $state = $transition['to'];
            if ($transition['approval']) {
                $approvalActions[] = $action;
            }
        }

        self::assertSame('completed', $state);
        self::assertSame(['request_approval'], $approvalActions);
    }

    public function test_completed_scenario_is_terminal(): void
    {
        $scenario = DemoScenario::create('01H00000000000000000000000', 'Demo');
        $scenario->currentState = 'completed';
        $scenario->status = 'completed';

        self::assertTrue($scenario->isComplete());
        self::assertArrayNotHasKey(
            'completed',
            array_column(DemoScenarioService::TRANSITIONS, null, 'from'),
        );
    }
}
