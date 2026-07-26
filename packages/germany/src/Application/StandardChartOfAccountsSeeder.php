<?php

declare(strict_types=1);

namespace Kontor\Germany\Application;

use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Domain\Account;

/**
 * The "chart of accounts" half of the "Germany package" milestone: seeds
 * a small, illustrative subset of the standard German SKR03 chart of
 * accounts through `kontor/ledger`'s own `ChartOfAccountsService` — not
 * the real SKR03 (several hundred accounts, and not freely
 * redistributable in full), just enough top-level accounts to prove a
 * country package actually integrates with the ledger foundations
 * rather than only declaring capability metadata.
 */
final class StandardChartOfAccountsSeeder
{
    /**
     * @var array<int, array{code: string, name: string, type: string}>
     */
    private const ACCOUNTS = [
        ['code' => '1000', 'name' => 'Kasse', 'type' => 'asset'],
        ['code' => '1200', 'name' => 'Bank', 'type' => 'asset'],
        ['code' => '1400', 'name' => 'Forderungen aus Lieferungen und Leistungen', 'type' => 'asset'],
        ['code' => '1600', 'name' => 'Verbindlichkeiten aus Lieferungen und Leistungen', 'type' => 'liability'],
        ['code' => '1700', 'name' => 'Umsatzsteuer', 'type' => 'liability'],
        ['code' => '2000', 'name' => 'Eigenkapital', 'type' => 'equity'],
        ['code' => '8000', 'name' => 'Erlöse', 'type' => 'revenue'],
        ['code' => '4000', 'name' => 'Aufwendungen', 'type' => 'expense'],
    ];

    public function __construct(
        private readonly ChartOfAccountsService $chartOfAccounts,
    ) {
    }

    /**
     * @return array<int, array{code: string, name: string, type: string}>
     */
    public function definitions(): array
    {
        return self::ACCOUNTS;
    }

    /**
     * @return Account[]
     */
    public function seed(string $organizationId, ?int $createdBy = null): array
    {
        $existingByCode = [];
        foreach (self::ACCOUNTS as $definition) {
            $existing = $this->chartOfAccounts->findByCode($organizationId, $definition['code']);
            if ($existing === null) {
                continue;
            }
            if ($existing->name !== $definition['name']
                || $existing->type !== $definition['type']
                || $existing->currencyCode !== 'EUR') {
                throw new \InvalidArgumentException(
                    "Account code \"{$definition['code']}\" already exists with incompatible German chart data."
                );
            }
            $existingByCode[$definition['code']] = $existing;
        }

        $accounts = [];
        foreach (self::ACCOUNTS as $definition) {
            $account = $existingByCode[$definition['code']] ?? null;
            if ($account === null) {
                $account = $this->chartOfAccounts->createAccount(
                    $organizationId,
                    $definition['code'],
                    $definition['name'],
                    $definition['type'],
                    'EUR',
                    createdBy: $createdBy,
                );
            } elseif ($account->isArchived()) {
                $this->chartOfAccounts->restore($account->uid->toString());
                $account = $this->chartOfAccounts->findByCode($organizationId, $definition['code'])
                    ?? throw new \RuntimeException('Restored German account could not be reloaded.');
            }
            $accounts[] = $account;
        }

        return $accounts;
    }
}
