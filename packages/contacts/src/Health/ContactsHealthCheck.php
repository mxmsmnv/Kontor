<?php

declare(strict_types=1);

namespace Kontor\Contacts\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Confirms the contacts/companies tables are reachable and reports basic
 * volume — a real query, not just a "table exists" check.
 */
final class ContactsHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'contacts';
    }

    public function run(): HealthCheckResult
    {
        try {
            $contacts = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_contacts WHERE deleted_at IS NULL')->fetchColumn();
            $companies = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_companies WHERE deleted_at IS NULL')->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$contacts} contact(s), {$companies} compan(y/ies).",
                ['contacts' => $contacts, 'companies' => $companies],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Contacts tables are not reachable: {$e->getMessage()}");
        }
    }
}
