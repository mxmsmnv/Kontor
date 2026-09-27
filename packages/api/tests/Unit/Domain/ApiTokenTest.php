<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Unit\Domain;

use Kontor\API\Domain\ApiToken;
use PHPUnit\Framework\TestCase;

final class ApiTokenTest extends TestCase
{
    public function test_empty_scopes_means_unrestricted(): void
    {
        $token = ApiToken::create('org_1', 'CI token', 'hash', []);

        $this->assertTrue($token->hasScope('anything'));
    }

    public function test_scoped_token_only_matches_its_own_scopes(): void
    {
        $token = ApiToken::create('org_1', 'CI token', 'hash', ['invoices:read']);

        $this->assertTrue($token->hasScope('invoices:read'));
        $this->assertFalse($token->hasScope('invoices:write'));
    }

    public function test_is_expired(): void
    {
        $expired = ApiToken::create('org_1', 't', 'hash', [], new \DateTimeImmutable('-1 day'));
        $notExpired = ApiToken::create('org_1', 't', 'hash', [], new \DateTimeImmutable('+1 day'));
        $noExpiry = ApiToken::create('org_1', 't', 'hash', []);

        $this->assertTrue($expired->isExpired());
        $this->assertFalse($notExpired->isExpired());
        $this->assertFalse($noExpiry->isExpired());
    }

    public function test_record_usage_updates_last_used_at(): void
    {
        $token = ApiToken::create('org_1', 't', 'hash');
        $this->assertNull($token->lastUsedAt);

        $token->recordUsage();

        $this->assertNotNull($token->lastUsedAt);
    }
}
