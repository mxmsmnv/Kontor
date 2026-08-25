<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\EntityActionResolver;
use PHPUnit\Framework\TestCase;

final class EntityActionResolverTest extends TestCase
{
    public function test_it_only_exposes_available_actions(): void
    {
        $actions = (new EntityActionResolver())->resolve('contact', 'contact 1', [
            'crm.lead.create' => true,
            'crm.deal.create' => false,
            'tasks.task.create' => true,
        ]);

        self::assertSame(['New lead', 'New linked task'], array_column($actions, 'label'));
        self::assertSame('crm-lead/?contact=contact%201', $actions[0]['route']);
        self::assertSame(
            'task/?entity_type=contact&entity_uid=contact%201',
            $actions[1]['route'],
        );
    }

    public function test_company_actions_use_the_company_parameter(): void
    {
        $actions = (new EntityActionResolver())->resolve('company', 'company-1', [
            'crm.deal.create' => true,
        ]);

        self::assertSame('crm-deal/?company=company-1', $actions[0]['route']);
    }

    public function test_unknown_entity_types_have_no_actions(): void
    {
        self::assertSame([], (new EntityActionResolver())->resolve('invoice', 'inv-1', [
            'crm.lead.create' => true,
            'tasks.task.create' => true,
        ]));
    }
}
