<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\API;

use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\DTO\ApiResourceSchema;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;

final class ContactResource implements ApiResourceInterface
{
    public function __construct(private readonly ContactRepository $contacts)
    {
    }

    public function key(): string
    {
        return 'contacts';
    }

    public function schema(): ApiResourceSchema
    {
        return new ApiResourceSchema(
            fields: [
                'uid' => 'string',
                'type' => 'string',
                'firstName' => 'string',
                'middleName' => 'string',
                'lastName' => 'string',
                'displayName' => 'string',
                'email' => 'string',
                'phone' => 'string',
                'mobile' => 'string',
                'jobTitle' => 'string',
                'preferredLanguage' => 'string',
                'preferredCurrency' => 'string',
                'source' => 'string',
                'status' => 'string',
                'assignedUserId' => 'int',
                'notes' => 'string',
                'metadata' => 'array',
            ],
            filterableFields: ['query', 'status'],
        );
    }

    public function list(string $organizationId, ApiQuery $query): ApiCollectionResult
    {
        $search = trim((string) ($query->filters['query'] ?? ''));
        $status = (string) ($query->filters['status'] ?? '');
        $offset = ($query->page - 1) * $query->pageSize;
        $rows = array_map(
            $this->present(...),
            $this->contacts->findAll($organizationId, $search, $query->pageSize, $offset, $status),
        );

        return new ApiCollectionResult(
            $rows,
            $this->contacts->countMatching($organizationId, $search, status: $status),
        );
    }

    public function find(string $organizationId, string $uid): ?array
    {
        $contact = $this->contacts->find($uid);

        return $contact !== null && hash_equals($contact->organizationId, $organizationId)
            ? $this->present($contact)
            : null;
    }

    public function create(string $organizationId, array $attributes): array
    {
        $contact = Contact::create(
            organizationId: $organizationId,
            firstName: $this->nullableString($attributes, 'firstName'),
            middleName: $this->nullableString($attributes, 'middleName'),
            lastName: $this->nullableString($attributes, 'lastName'),
            displayName: $this->nullableString($attributes, 'displayName'),
            type: $this->string($attributes, 'type', 'individual'),
            email: $this->nullableString($attributes, 'email', lowercase: true),
            phone: $this->nullableString($attributes, 'phone'),
            mobile: $this->nullableString($attributes, 'mobile'),
            jobTitle: $this->nullableString($attributes, 'jobTitle'),
            preferredLanguage: $this->string($attributes, 'preferredLanguage', 'en'),
            preferredCurrency: $this->nullableString($attributes, 'preferredCurrency'),
            source: $this->nullableString($attributes, 'source'),
            status: $this->string($attributes, 'status', 'active'),
            assignedUserId: isset($attributes['assignedUserId']) ? (int) $attributes['assignedUserId'] : null,
            notes: $this->nullableString($attributes, 'notes'),
            metadata: is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [],
        );
        $this->contacts->save($contact);

        return $this->present($contact);
    }

    public function update(string $organizationId, string $uid, array $attributes): array
    {
        $contact = $this->requireOwned($organizationId, $uid);
        foreach (
            [
                'type' => 'type',
                'firstName' => 'firstName',
                'middleName' => 'middleName',
                'lastName' => 'lastName',
                'displayName' => 'displayName',
                'email' => 'email',
                'phone' => 'phone',
                'mobile' => 'mobile',
                'jobTitle' => 'jobTitle',
                'preferredLanguage' => 'preferredLanguage',
                'preferredCurrency' => 'preferredCurrency',
                'source' => 'source',
                'status' => 'status',
                'notes' => 'notes',
            ] as $attribute => $property
        ) {
            if (array_key_exists($attribute, $attributes)) {
                $value = $this->nullableString($attributes, $attribute, lowercase: $attribute === 'email');
                $contact->{$property} = in_array($attribute, ['type', 'displayName', 'preferredLanguage', 'status'], true)
                    ? ($value ?? '')
                    : $value;
            }
        }
        if (array_key_exists('assignedUserId', $attributes)) {
            $contact->assignedUserId = $attributes['assignedUserId'] !== null
                ? (int) $attributes['assignedUserId']
                : null;
        }
        if (array_key_exists('metadata', $attributes) && is_array($attributes['metadata'])) {
            $contact->metadata = $attributes['metadata'];
        }
        if (
            !array_key_exists('displayName', $attributes)
            && array_intersect(['firstName', 'middleName', 'lastName'], array_keys($attributes)) !== []
        ) {
            $contact->displayName = Contact::composeDisplayName(
                $contact->firstName,
                $contact->middleName,
                $contact->lastName,
            );
        }

        $this->contacts->save($contact);

        return $this->present($contact);
    }

    public function delete(string $organizationId, string $uid): void
    {
        $this->requireOwned($organizationId, $uid);
        $this->contacts->delete($uid);
    }

    private function requireOwned(string $organizationId, string $uid): Contact
    {
        $contact = $this->contacts->require($uid);
        if (!hash_equals($contact->organizationId, $organizationId)) {
            throw new \RuntimeException("Contact \"{$uid}\" was not found.");
        }

        return $contact;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Contact $contact): array
    {
        return [
            'uid' => $contact->uid->toString(),
            'type' => $contact->type,
            'firstName' => $contact->firstName,
            'middleName' => $contact->middleName,
            'lastName' => $contact->lastName,
            'displayName' => $contact->displayName,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'mobile' => $contact->mobile,
            'jobTitle' => $contact->jobTitle,
            'preferredLanguage' => $contact->preferredLanguage,
            'preferredCurrency' => $contact->preferredCurrency,
            'source' => $contact->source,
            'status' => $contact->status,
            'assignedUserId' => $contact->assignedUserId,
            'notes' => $contact->notes,
            'metadata' => $contact->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function string(array $attributes, string $key, string $default): string
    {
        return $this->nullableString($attributes, $key) ?? $default;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function nullableString(array $attributes, string $key, bool $lowercase = false): ?string
    {
        if (!array_key_exists($key, $attributes) || $attributes[$key] === null) {
            return null;
        }

        $value = trim((string) $attributes[$key]);
        if ($value === '') {
            return null;
        }

        return $lowercase ? strtolower($value) : $value;
    }
}
