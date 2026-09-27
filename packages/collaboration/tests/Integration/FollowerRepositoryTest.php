<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Integration;

use Kontor\Collaboration\Infrastructure\Persistence\FollowerRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class FollowerRepositoryTest extends DatabaseTestCase
{
    private function repository(): FollowerRepository
    {
        return new FollowerRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_follow_is_idempotent(): void
    {
        $repository = $this->repository();
        $repository->follow($this->organizationUid, 'crm_deal', 'deal_01', 5);
        $repository->follow($this->organizationUid, 'crm_deal', 'deal_01', 5);

        $this->assertCount(1, $repository->followersOf('crm_deal', 'deal_01'));
    }

    public function test_unfollow_removes_the_row(): void
    {
        $repository = $this->repository();
        $repository->follow($this->organizationUid, 'crm_deal', 'deal_01', 5);

        $repository->unfollow($this->organizationUid, 'crm_deal', 'deal_01', 5);

        $this->assertFalse($repository->isFollowing($this->organizationUid, 'crm_deal', 'deal_01', 5));
    }

    public function test_followers_of_lists_every_follower(): void
    {
        $repository = $this->repository();
        $repository->follow($this->organizationUid, 'crm_deal', 'deal_01', 5);
        $repository->follow($this->organizationUid, 'crm_deal', 'deal_01', 6);

        $userIds = array_map(fn ($f) => $f->userId, $repository->followersOf('crm_deal', 'deal_01'));

        $this->assertSame([5, 6], $userIds);
    }
}
