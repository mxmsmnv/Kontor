<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Unit\Domain;

use Kontor\Portal\Domain\PortalAccount;
use PHPUnit\Framework\TestCase;

final class PortalAccountTest extends TestCase
{
    public function test_create_defaults_to_active_with_no_login_yet(): void
    {
        $account = PortalAccount::create('org_1', 'contact_1', 'customer@example.com', 'hash');

        $this->assertTrue($account->isActive());
        $this->assertNull($account->lastLoginAt);
    }

    public function test_record_login_sets_last_login_at(): void
    {
        $account = PortalAccount::create('org_1', 'contact_1', 'customer@example.com', 'hash');

        $account->recordLogin();

        $this->assertNotNull($account->lastLoginAt);
    }

    public function test_disable(): void
    {
        $account = PortalAccount::create('org_1', 'contact_1', 'customer@example.com', 'hash');

        $account->disable();

        $this->assertFalse($account->isActive());
        $this->assertSame('disabled', $account->status);
    }
}
