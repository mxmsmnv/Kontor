<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\AuditChangePresenter;
use Kontor\Core\Domain\AuditEvent;
use PHPUnit\Framework\TestCase;

final class AuditChangePresenterTest extends TestCase
{
    public function test_presents_only_changed_fields_with_readable_values(): void
    {
        $event = $this->event(
            previous: [
                'displayName' => 'Ada',
                'active' => false,
                'unchanged' => 'same',
                'removed_field' => 'old',
            ],
            current: [
                'displayName' => 'Ada Lovelace',
                'active' => true,
                'unchanged' => 'same',
                'tags' => ['vip', 'research'],
            ],
        );

        $this->assertSame([
            ['field' => 'Display Name', 'previous' => 'Ada', 'current' => 'Ada Lovelace'],
            ['field' => 'Active', 'previous' => 'No', 'current' => 'Yes'],
            ['field' => 'Removed Field', 'previous' => 'old', 'current' => 'Not set'],
            ['field' => 'Tags', 'previous' => 'Not set', 'current' => '["vip","research"]'],
        ], (new AuditChangePresenter())->changes($event));
    }

    public function test_distinguishes_null_from_a_missing_value(): void
    {
        $changes = (new AuditChangePresenter())->changes($this->event(
            previous: [],
            current: ['jobTitle' => null],
        ));

        $this->assertSame([
            ['field' => 'Job Title', 'previous' => 'Not set', 'current' => 'Null'],
        ], $changes);
    }

    private function event(?array $previous, ?array $current): AuditEvent
    {
        return new AuditEvent(
            uid: 'event_01',
            component: 'contacts',
            entityType: 'contact',
            entityUid: 'contact_01',
            action: 'updated',
            actorType: 'user',
            actorUid: 'user_01',
            occurredAt: new \DateTimeImmutable('2026-07-26T12:30:00+00:00'),
            previous: $previous,
            current: $current,
            metadata: [],
        );
    }
}
