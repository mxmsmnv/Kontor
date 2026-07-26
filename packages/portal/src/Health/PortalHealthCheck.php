<?php

declare(strict_types=1);

namespace Kontor\Portal\Health;

use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Portal\Infrastructure\Persistence\PortalAccountRepository;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Beyond a plain count: every active portal account should still resolve
 * to a real Contact — one could go missing out from under it via
 * `kontor/contacts`'s own GDPR erasure (`ContactRepository::delete()`),
 * which this package has no way to prevent and must instead detect — the
 * same "check for a real anomaly" approach `kontor/entities`'
 * `EntitiesHealthCheck` uses for its own orphaned-record check.
 */
final class PortalHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly PortalAccountRepository $accounts,
        private readonly ContactRepository $contacts,
    ) {
    }

    public function key(): string
    {
        return 'portal';
    }

    public function run(): HealthCheckResult
    {
        try {
            $accounts = $this->accounts->activeAccountsSummary();
            $orphaned = 0;

            foreach ($accounts as $account) {
                if ($this->contacts->find($account['contactUid']) === null) {
                    $orphaned++;
                }
            }

            if ($orphaned === 0) {
                return new HealthCheckResult(
                    'ok',
                    count($accounts).' active portal account(s), every one with a valid linked contact.',
                    ['activeAccounts' => count($accounts), 'orphanedAccounts' => 0],
                );
            }

            return new HealthCheckResult(
                'warning',
                "{$orphaned} portal account(s) reference a missing contact.",
                ['activeAccounts' => count($accounts), 'orphanedAccounts' => $orphaned],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Portal tables are not reachable: {$e->getMessage()}");
        }
    }
}
