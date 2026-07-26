<?php

declare(strict_types=1);

namespace Kontor\Portal\Application;

use InvalidArgumentException;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;

/**
 * The "profile" milestone: a customer views/updates their own linked
 * `kontor/contacts` Contact — restricted to a safe field allowlist rather
 * than every field the staff-facing CRM can change (no `status`,
 * `assignedUserId`, `source`, etc.). An unknown/disallowed field is a
 * hard error, not silently ignored.
 */
final class CustomerProfileService
{
    /**
     * @var string[]
     */
    private const EDITABLE_FIELDS = ['firstName', 'middleName', 'lastName', 'phone', 'mobile', 'preferredLanguage'];

    public function __construct(
        private readonly ContactRepository $contacts,
    ) {
    }

    public function view(string $contactUid): Contact
    {
        return $this->contacts->require($contactUid);
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function update(string $contactUid, array $changes): Contact
    {
        $contact = $this->contacts->require($contactUid);

        foreach ($changes as $field => $value) {
            if (!in_array($field, self::EDITABLE_FIELDS, true)) {
                throw new InvalidArgumentException("Field \"{$field}\" cannot be changed through the customer portal.");
            }

            $contact->{$field} = $value;
        }

        $this->contacts->save($contact);

        return $contact;
    }
}
