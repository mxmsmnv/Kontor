<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Persistence;

use Kontor\Core\Domain\Organization;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * SQL repository for kontor_organizations (kontor.md#11.1). Public callers
 * address organizations by uid only, never the internal numeric id
 * (kontor.md#10.3).
 */
final class OrganizationRepository
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function find(string $uid): ?Organization
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_organizations WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): Organization
    {
        return $this->find($uid) ?? throw new RuntimeException("Organization \"{$uid}\" was not found.");
    }

    /**
     * Resolves the internal kontor_organizations.id for a uid. Only
     * infrastructure code populating an organization_id FK column
     * (kontor.md#10.4) needs this — domain and API layers use the uid.
     */
    public function internalIdOf(string $uid): int
    {
        $statement = $this->pdo->prepare('SELECT id FROM kontor_organizations WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("Organization \"{$uid}\" was not found.");
        }

        return (int) $id;
    }

    public function save(Organization $organization): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_organizations
                (uid, name, legal_name, country_code, default_language, default_currency, timezone, status, settings_json, created_at, updated_at)
             VALUES
                (:uid, :name, :legal_name, :country_code, :default_language, :default_currency, :timezone, :status, :settings_json, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                legal_name = VALUES(legal_name),
                country_code = VALUES(country_code),
                default_language = VALUES(default_language),
                default_currency = VALUES(default_currency),
                timezone = VALUES(timezone),
                status = VALUES(status),
                settings_json = VALUES(settings_json),
                updated_at = VALUES(updated_at),
                version = version + 1'
        );

        $statement->execute([
            'uid' => $organization->uid->toString(),
            'name' => $organization->name,
            'legal_name' => $organization->legalName,
            'country_code' => $organization->countryCode,
            'default_language' => $organization->defaultLanguage,
            'default_currency' => $organization->defaultCurrency,
            'timezone' => $organization->timezone,
            'status' => $organization->status,
            'settings_json' => json_encode($organization->settings, JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Returns the single default organization for single-company installs
     * (kontor.md#4.1), creating it on first use.
     */
    public function defaultOrganization(string $countryCode, string $defaultLanguage, string $defaultCurrency): Organization
    {
        $statement = $this->pdo->query('SELECT * FROM kontor_organizations ORDER BY id ASC LIMIT 1');
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row !== false) {
            return $this->hydrate($row);
        }

        $organization = Organization::createDefault($countryCode, $defaultLanguage, $defaultCurrency);
        $this->save($organization);

        return $organization;
    }

    private function hydrate(array $row): Organization
    {
        return new Organization(
            uid: Uid::fromString($row['uid']),
            name: $row['name'],
            legalName: $row['legal_name'],
            countryCode: $row['country_code'],
            defaultLanguage: $row['default_language'],
            defaultCurrency: $row['default_currency'],
            timezone: $row['timezone'],
            status: $row['status'],
            settings: $row['settings_json'] !== null ? json_decode($row['settings_json'], true) : [],
        );
    }
}
