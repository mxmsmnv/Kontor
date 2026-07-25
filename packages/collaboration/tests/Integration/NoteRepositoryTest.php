<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Integration;

use Kontor\Collaboration\Domain\Note;
use Kontor\Collaboration\Infrastructure\Persistence\NoteRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class NoteRepositoryTest extends DatabaseTestCase
{
    private function repository(): NoteRepository
    {
        return new NoteRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $note = Note::create($this->organizationUid, 'contact', 'ct_01', 'Prefers email over phone.', createdBy: 7);

        $repository->save($note);
        $found = $repository->find($note->uid->toString());

        $this->assertNotNull($found);
        $this->assertSame('Prefers email over phone.', $found->body);
        $this->assertSame(7, $found->createdBy);
    }

    public function test_for_entity_returns_newest_first_and_excludes_archived(): void
    {
        $repository = $this->repository();
        $first = Note::create($this->organizationUid, 'contact', 'ct_01', 'First note');
        $repository->save($first);
        $second = Note::create($this->organizationUid, 'contact', 'ct_01', 'Second note');
        $repository->save($second);

        $notes = $repository->forEntity('contact', 'ct_01');
        $this->assertCount(2, $notes);
        $this->assertSame('Second note', $notes[0]->body);

        $repository->archive($first->uid->toString());
        $this->assertCount(1, $repository->forEntity('contact', 'ct_01'));
    }
}
