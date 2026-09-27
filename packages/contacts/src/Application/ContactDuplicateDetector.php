<?php

declare(strict_types=1);

namespace Kontor\Contacts\Application;

use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;

/**
 * Substage 3.1 "duplicate detection" — exact email/phone matching within
 * an organization, per kontor.md#12.1's indexed email/phone columns. Used
 * both standalone (before creating a contact via the admin/API) and by
 * ContactImportProvider::findExisting() during import.
 */
final class ContactDuplicateDetector
{
    public function __construct(private readonly ContactRepository $contacts)
    {
    }

    /**
     * @return Contact[] potential duplicates, most confident match first
     */
    public function findDuplicates(string $organizationUid, ?string $email, ?string $phone): array
    {
        $matches = [];

        if ($email !== null && $email !== '') {
            $byEmail = $this->contacts->findByEmail($organizationUid, $email);

            if ($byEmail !== null) {
                $matches[$byEmail->uid->toString()] = $byEmail;
            }
        }

        if ($phone !== null && $phone !== '') {
            $byPhone = $this->contacts->findByPhone($organizationUid, $phone);

            if ($byPhone !== null) {
                $matches[$byPhone->uid->toString()] = $byPhone;
            }
        }

        return array_values($matches);
    }

    public function hasDuplicate(string $organizationUid, ?string $email, ?string $phone): bool
    {
        return $this->findDuplicates($organizationUid, $email, $phone) !== [];
    }
}
