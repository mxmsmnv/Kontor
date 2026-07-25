<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\ExtensionRepository;
use Kontor\Core\Tests\Integration\DatabaseTestCase;

final class ExtensionRepositoryTest extends DatabaseTestCase
{
    public function test_set_then_get_round_trips(): void
    {
        $extensions = new ExtensionRepository($this->pdo);

        $extensions->set(1, 'KontorContacts', 'contact', 'ct_01', 'tags', ['vip', 'lead']);

        $this->assertSame(['vip', 'lead'], $extensions->get(1, 'KontorContacts', 'contact', 'ct_01', 'tags'));
    }

    public function test_get_returns_null_when_missing(): void
    {
        $extensions = new ExtensionRepository($this->pdo);

        $this->assertNull($extensions->get(1, 'KontorContacts', 'contact', 'ct_missing', 'tags'));
    }

    public function test_set_upserts_rather_than_duplicating(): void
    {
        $extensions = new ExtensionRepository($this->pdo);

        $extensions->set(1, 'KontorContacts', 'contact', 'ct_01', 'tags', ['vip']);
        $extensions->set(1, 'KontorContacts', 'contact', 'ct_01', 'tags', ['vip', 'lead']);

        $this->assertSame(['vip', 'lead'], $extensions->get(1, 'KontorContacts', 'contact', 'ct_01', 'tags'));

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_extensions')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_delete_removes_the_entry(): void
    {
        $extensions = new ExtensionRepository($this->pdo);
        $extensions->set(1, 'KontorContacts', 'contact', 'ct_01', 'tags', ['vip']);

        $extensions->delete(1, 'KontorContacts', 'contact', 'ct_01', 'tags');

        $this->assertNull($extensions->get(1, 'KontorContacts', 'contact', 'ct_01', 'tags'));
    }

    public function test_all_for_returns_every_key_for_that_entity(): void
    {
        $extensions = new ExtensionRepository($this->pdo);
        $extensions->set(1, 'KontorContacts', 'contact', 'ct_01', 'tags', ['vip']);
        $extensions->set(1, 'KontorContacts', 'contact', 'ct_01', 'preferences', ['newsletter' => true]);

        $all = $extensions->allFor(1, 'KontorContacts', 'contact', 'ct_01');

        $this->assertSame(['tags' => ['vip'], 'preferences' => ['newsletter' => true]], $all);
    }

    public function test_different_components_do_not_collide_on_the_same_key(): void
    {
        $extensions = new ExtensionRepository($this->pdo);
        $extensions->set(1, 'KontorContacts', 'contact', 'ct_01', 'tags', ['from-contacts']);
        $extensions->set(1, 'KontorCRM', 'contact', 'ct_01', 'tags', ['from-crm']);

        $this->assertSame(['from-contacts'], $extensions->get(1, 'KontorContacts', 'contact', 'ct_01', 'tags'));
        $this->assertSame(['from-crm'], $extensions->get(1, 'KontorCRM', 'contact', 'ct_01', 'tags'));
    }
}
