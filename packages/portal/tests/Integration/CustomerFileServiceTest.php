<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Files\Migrations\Migration0001CreateFilesTable;
use Kontor\Portal\Application\CustomerFileService;

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

    public function test_files_for_document_lists_attached_files(): void
    {
        $organizationId = (new OrganizationRepository($this->pdo))->internalIdOf($this->organizationUid);
        $files = $this->files();
        $files->insert($organizationId, 'local', 'org/invoice1/statement.pdf', 'statement.pdf', 'application/pdf', 1024, hash('sha256', 'x'), 'private', null, 'invoice', 'invoice_1', 1, [], null);

        $service = new CustomerFileService($files, new SignedUrlSigner('secret', 'https://example.com/portal/files/download'));
        $found = $service->filesForDocument('invoice', 'invoice_1');

        $this->assertCount(1, $found);
        $this->assertSame('statement.pdf', $found[0]['original_name']);
    }

    public function test_download_url_is_signed_for_the_files_own_path(): void
    {
        $organizationId = (new OrganizationRepository($this->pdo))->internalIdOf($this->organizationUid);
        $files = $this->files();
        $uid = $files->insert($organizationId, 'local', 'org/invoice1/statement.pdf', 'statement.pdf', 'application/pdf', 1024, hash('sha256', 'x'), 'private', null, 'invoice', 'invoice_1', 1, [], null);

        $service = new CustomerFileService($files, new SignedUrlSigner('secret', 'https://example.com/portal/files/download'));
        $url = $service->downloadUrl($uid, new \DateTimeImmutable('+10 minutes'));

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('org/invoice1/statement.pdf', $query['path']);
    }

    public function test_download_url_for_a_missing_file_throws(): void
    {
        $service = new CustomerFileService($this->files(), new SignedUrlSigner('secret', 'https://example.com/portal/files/download'));

        $this->expectException(\RuntimeException::class);

        $service->downloadUrl('not_a_real_uid', new \DateTimeImmutable('+10 minutes'));
    }
}
