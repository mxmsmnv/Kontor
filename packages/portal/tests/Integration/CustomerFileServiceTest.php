<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Files\Migrations\Migration0001CreateFilesTable;
use Kontor\Portal\Application\CustomerFileService;
use Kontor\SDK\ValueObjects\Uid;

final class CustomerFileServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateFilesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_files', 'kontor_organizations', 'kontor_migrations'];
    }

    private function files(): FileRepository
    {
        return new FileRepository($this->pdo);
    }

    private function service(FileRepository $files, int $organizationId): CustomerFileService
    {
        return new CustomerFileService(
            $files,
            new SignedUrlSigner('secret', 'https://example.com/portal/files/download'),
            $organizationId,
        );
    }

    public function test_files_for_document_lists_attached_files(): void
    {
        $organizationId = (new OrganizationRepository($this->pdo))->internalIdOf($this->organizationUid);
        $files = $this->files();
        $files->insert($organizationId, 'local', 'org/invoice1/statement.pdf', 'statement.pdf', 'application/pdf', 1024, hash('sha256', 'x'), 'private', null, 'invoice', 'invoice_1', 1, [], null);

        $service = $this->service($files, $organizationId);
        $found = $service->filesForDocument('invoice', 'invoice_1');

        $this->assertCount(1, $found);
        $this->assertSame('statement.pdf', $found[0]['original_name']);
    }

    public function test_download_url_is_signed_for_the_files_own_path(): void
    {
        $organizationId = (new OrganizationRepository($this->pdo))->internalIdOf($this->organizationUid);
        $files = $this->files();
        $uid = $files->insert($organizationId, 'local', 'org/invoice1/statement.pdf', 'statement.pdf', 'application/pdf', 1024, hash('sha256', 'x'), 'private', null, 'invoice', 'invoice_1', 1, [], null);

        $service = $this->service($files, $organizationId);
        $url = $service->downloadUrl($uid, new \DateTimeImmutable('+10 minutes'));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('org/invoice1/statement.pdf', $query['path']);
    }

    public function test_download_url_for_a_missing_file_throws(): void
    {
        $organizationId = (new OrganizationRepository($this->pdo))->internalIdOf($this->organizationUid);
        $service = $this->service($this->files(), $organizationId);

        $this->expectException(\RuntimeException::class);

        $service->downloadUrl('not_a_real_uid', new \DateTimeImmutable('+10 minutes'));
    }

    public function test_files_for_document_excludes_another_organizations_files(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $currentOrganizationId = $organizations->internalIdOf($this->organizationUid);
        $other = new Organization(
            uid: Uid::generate(),
            name: 'Other organization',
            legalName: null,
            countryCode: 'US',
            defaultLanguage: 'en',
            defaultCurrency: 'USD',
            timezone: 'UTC',
            status: 'active',
        );
        $organizations->save($other);
        $otherOrganizationId = $organizations->internalIdOf($other->uid->toString());
        $files = $this->files();
        $files->insert($otherOrganizationId, 'local', 'other/invoice1/private.pdf', 'private.pdf', 'application/pdf', 10, hash('sha256', 'private'), 'private', null, 'invoice', 'invoice_1', 1, [], null);

        $found = $this->service($files, $currentOrganizationId)->filesForDocument('invoice', 'invoice_1');

        $this->assertSame([], $found);
    }

    public function test_download_url_rejects_another_organizations_file(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $currentOrganizationId = $organizations->internalIdOf($this->organizationUid);
        $other = new Organization(
            uid: Uid::generate(),
            name: 'Other organization',
            legalName: null,
            countryCode: 'US',
            defaultLanguage: 'en',
            defaultCurrency: 'USD',
            timezone: 'UTC',
            status: 'active',
        );
        $organizations->save($other);
        $otherOrganizationId = $organizations->internalIdOf($other->uid->toString());
        $files = $this->files();
        $fileUid = $files->insert($otherOrganizationId, 'local', 'other/invoice1/private.pdf', 'private.pdf', 'application/pdf', 10, hash('sha256', 'private'), 'private', null, 'invoice', 'invoice_1', 1, [], null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('was not found');

        $this->service($files, $currentOrganizationId)->downloadUrl(
            $fileUid,
            new \DateTimeImmutable('+10 minutes'),
        );
    }
}
