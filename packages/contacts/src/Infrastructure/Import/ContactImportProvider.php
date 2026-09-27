<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\Import;

use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\SDK\Contracts\ImportProviderInterface;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;

/**
 * kontor.md#9.6. Validation errors are translation-key codes (e.g.
 * "contact.email.invalid"), not English sentences — kontor.md section 23:
 * "Machine identifiers remain English and stable" — resolved to text via
 * resources/translations/{lang}/messages.json at presentation time.
 */
final class ContactImportProvider implements ImportProviderInterface
{
    public function __construct(
        private readonly ContactRepository $contacts,
        private readonly ContactDuplicateDetector $duplicates,
    ) {
    }

    public function entityType(): string
    {
        return 'contact';
    }

    public function fields(): array
    {
        return [
            'type', 'first_name', 'middle_name', 'last_name', 'display_name',
            'email', 'phone', 'mobile', 'job_title', 'source', 'status',
        ];
    }

    public function validate(array $record, ImportContext $context): ValidationResult
    {
        $errors = [];

        $firstName = $this->stringOrNull($record['first_name'] ?? null);
        $lastName = $this->stringOrNull($record['last_name'] ?? null);
        $displayName = $this->stringOrNull($record['display_name'] ?? null);

        if ($firstName === null && $lastName === null && $displayName === null) {
            $errors['display_name'][] = 'contact.display_name.required';
        }

        $email = $this->stringOrNull($record['email'] ?? null);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'][] = 'contact.email.invalid';
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid($errors);
    }

    public function findExisting(array $record, ImportContext $context): ?string
    {
        $duplicates = $this->duplicates->findDuplicates(
            $context->organizationId,
            $this->stringOrNull($record['email'] ?? null),
            $this->stringOrNull($record['phone'] ?? null),
        );

        return $duplicates === [] ? null : $duplicates[0]->uid->toString();
    }

    public function import(array $record, ImportContext $context): ImportRecordResult
    {
        $existingUid = $this->findExisting($record, $context);

        if ($existingUid !== null) {
            $contact = $this->contacts->require($existingUid);
            $this->applyRecord($contact, $record);
            $this->contacts->save($contact);

            return new ImportRecordResult('updated', $existingUid);
        }

        $contact = Contact::create(
            organizationId: $context->organizationId,
            firstName: $this->stringOrNull($record['first_name'] ?? null),
            middleName: $this->stringOrNull($record['middle_name'] ?? null),
            lastName: $this->stringOrNull($record['last_name'] ?? null),
            displayName: $this->stringOrNull($record['display_name'] ?? null),
            type: $record['type'] ?? 'individual',
            email: $this->stringOrNull($record['email'] ?? null),
            phone: $this->stringOrNull($record['phone'] ?? null),
            mobile: $this->stringOrNull($record['mobile'] ?? null),
            jobTitle: $this->stringOrNull($record['job_title'] ?? null),
            source: $this->stringOrNull($record['source'] ?? null),
            status: $record['status'] ?? 'active',
        );

        $this->contacts->save($contact);

        return new ImportRecordResult('created', $contact->uid->toString());
    }

    /**
     * @param array<string, mixed> $record
     */
    private function applyRecord(Contact $contact, array $record): void
    {
        foreach (['first_name' => 'firstName', 'middle_name' => 'middleName', 'last_name' => 'lastName'] as $field => $property) {
            if (array_key_exists($field, $record)) {
                $contact->$property = $this->stringOrNull($record[$field]);
            }
        }

        if (array_key_exists('display_name', $record) && $this->stringOrNull($record['display_name']) !== null) {
            $contact->displayName = $record['display_name'];
        } elseif (array_key_exists('first_name', $record) || array_key_exists('last_name', $record)) {
            $contact->displayName = Contact::composeDisplayName($contact->firstName, $contact->middleName, $contact->lastName);
        }

        foreach (['email', 'phone', 'mobile', 'source', 'status'] as $field) {
            if (array_key_exists($field, $record)) {
                $contact->$field = $this->stringOrNull($record[$field]) ?? $record[$field];
            }
        }

        if (array_key_exists('job_title', $record)) {
            $contact->jobTitle = $this->stringOrNull($record['job_title']);
        }
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
