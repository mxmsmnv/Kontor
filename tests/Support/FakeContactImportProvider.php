<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Support;

use Kontor\SDK\Contracts\ImportProviderInterface;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;

/**
 * Shared fake used by both ImportManagerTest (unit) and
 * ImportManagerRollbackTest (integration) — lives under tests/Support
 * rather than inside a single test class file so it autoloads correctly
 * whichever testsuite runs, not only when both happen to run together.
 */
final class FakeContactImportProvider implements ImportProviderInterface
{
    public int $findExistingCalls = 0;
    public int $importCalls = 0;

    public function entityType(): string
    {
        return 'contact';
    }

    public function fields(): array
    {
        return ['name', 'email'];
    }

    public function validate(array $record, ImportContext $context): ValidationResult
    {
        if (($record['email'] ?? '') === '') {
            return ValidationResult::invalid(['email' => ['is required']]);
        }

        return ValidationResult::valid();
    }

    public function findExisting(array $record, ImportContext $context): ?string
    {
        $this->findExistingCalls++;

        return $record['email'] === 'existing@widgets.test' ? 'cnt_existing_01' : null;
    }

    public function import(array $record, ImportContext $context): ImportRecordResult
    {
        $this->importCalls++;
        $existingUid = $this->findExisting($record, $context);

        return $existingUid !== null
            ? new ImportRecordResult('updated', entityUid: $existingUid)
            : new ImportRecordResult('created', entityUid: 'cnt_new_' . $this->importCalls);
    }
}
