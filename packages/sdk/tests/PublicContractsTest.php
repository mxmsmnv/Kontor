<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests;

use Kontor\SDK\Events\KontorEvent;
use Kontor\SDK\ValueObjects\OrganizationId;
use Kontor\SDK\ValueObjects\Uid;
use PHPUnit\Framework\TestCase;

final class PublicContractsTest extends TestCase
{
    public function test_organization_id_round_trips_through_both_public_factories(): void
    {
        $value = '01KYG385JXNQQ8H16SH37VNG3V';
        $uid = Uid::fromString($value);
        $fromUid = OrganizationId::fromUid($uid);
        $fromString = OrganizationId::fromString(strtolower($value));

        $this->assertSame($uid, $fromUid->uid());
        $this->assertSame($value, (string) $fromString);
        $this->assertTrue($fromUid->equals($fromString));
    }

    public function test_event_array_and_json_expose_the_same_canonical_envelope(): void
    {
        $correlation = Uid::fromString('01KYG385JXNQQ8H16SH37VNG3V');
        $causation = Uid::fromString('01KYG385K3FX93BZPYTM5GEZQQ');
        $event = KontorEvent::create(
            event: 'invoice.issued',
            organizationId: '01KYG385M1FAPYMS8E3DM9E10A',
            entityType: 'invoice',
            entityId: '01KYG385M8D6NV9EGB2H0T7W6Q',
            actorType: 'user',
            actorId: '42',
            data: ['total_minor' => 12500],
            correlationId: $correlation,
            causationId: $causation,
            version: '2.0',
        );

        $array = $event->toArray();

        $this->assertSame('invoice.issued', $array['event']);
        $this->assertSame('2.0', $array['version']);
        $this->assertTrue(Uid::isValid($array['eventId']));
        $this->assertSame($correlation->toString(), $array['correlationId']);
        $this->assertSame($causation->toString(), $array['causationId']);
        $this->assertSame(['total_minor' => 12500], $array['data']);
        $this->assertSame($array, json_decode($event->toJson(), true, flags: JSON_THROW_ON_ERROR));
    }
}
