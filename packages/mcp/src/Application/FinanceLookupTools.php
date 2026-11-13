<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Ledger\Domain\LedgerEntry;
use Kontor\Payments\Domain\Payment;
use ProcessWire\Wire404Exception;
use ProcessWire\WirePermissionException;

trait FinanceLookupTools
{
    private function financeLookupTools(): array
    {
        $tools = [];
        foreach (['invoice' => 'KontorInvoices', 'payment' => 'KontorPayments'] as $kind => $module) {
            if (!$this->wire()->modules->isInstalled($module)) {
                continue;
            }
            $tools[] = $this->tool(
                'kontor_' . $kind . 's_list',
                'List Kontor ' . $kind . 's',
                'Search visible ' . $kind . ' summaries in the active organization.',
                $kind === 'invoice' ? $this->mcpInvoicesList(...) : $this->mcpPaymentsList(...),
                'read',
                [
                    'query' => ['type' => 'string', 'maxLength' => 160],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ],
            );
            $tools[] = $this->tool(
                'kontor_' . $kind . '_get',
                'Read a Kontor ' . $kind,
                'Read the status and amounts for one ' . $kind . ' in the active organization.',
                $kind === 'invoice' ? $this->mcpInvoiceGet(...) : $this->mcpPaymentGet(...),
                'read',
                ['uid' => $this->uidSchema()],
                ['uid'],
            );
        }
        if ($this->wire()->modules->isInstalled('KontorLedger')) {
            $tools[] = $this->tool(
                'kontor_ledger_entries_list', 'List Kontor ledger entries',
                'Read a page of ledger entry summaries under the Workspace ledger permission.',
                $this->mcpLedgerEntriesList(...), 'read',
                ['offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000]],
            );
        }
        return $tools;
    }

    public function mcpLedgerEntriesList(int $offset = 0): array
    {
        $actor = $this->mcpActor();
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-ledger-entry-view'))) {
            throw new WirePermissionException('The linked user cannot view ledger entries.');
        }
        $modules = $this->wire()->modules;
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();
        $entries = $modules->get('KontorLedger')->entryRepository()->forOrganization($organization, 26, $offset);

        return [
            'items' => array_map(static fn (LedgerEntry $entry): array => [
                'uid' => $entry->uid->toString(),
                'description' => $entry->description,
                'entry_date' => $entry->entryDate->format('Y-m-d'),
                'reference_type' => $entry->referenceType,
            ], array_slice($entries, 0, 25)),
            'next_offset' => count($entries) > 25 && $offset + 25 <= 10000 ? $offset + 25 : null,
        ];
    }

    public function mcpInvoicesList(string $query = '', int $offset = 0): array
    {
        return $this->mcpFinanceList('invoice', $query, $offset);
    }

    public function mcpPaymentsList(string $query = '', int $offset = 0): array
    {
        return $this->mcpFinanceList('payment', $query, $offset);
    }

    public function mcpInvoiceGet(string $uid): array
    {
        return $this->mcpFinanceGet('invoice', $uid);
    }

    public function mcpPaymentGet(string $uid): array
    {
        return $this->mcpFinanceGet('payment', $uid);
    }

    private function mcpFinanceList(string $kind, string $query, int $offset): array
    {
        [$repository, $organizationUid] = $this->mcpFinanceContext($kind);
        $records = $repository->findMatching($organizationUid, $query, limit: 26, offset: $offset);

        return [
            'items' => array_map($this->mcpFinanceRecord(...), array_slice($records, 0, 25)),
            'next_offset' => count($records) > 25 && $offset + 25 <= 10000 ? $offset + 25 : null,
        ];
    }

    private function mcpFinanceGet(string $kind, string $uid): array
    {
        [$repository, $organizationUid] = $this->mcpFinanceContext($kind);
        $record = $repository->find($uid);
        if ($record === null || $record->organizationId !== $organizationUid) {
            throw new Wire404Exception('Financial record not found.');
        }
        return ['record' => $this->mcpFinanceRecord($record)];
    }

    private function mcpFinanceContext(string $kind): array
    {
        $actor = $this->mcpActor();
        $permission = 'kontor-' . $kind . 's-' . $kind . '-view';
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission($permission))) {
            throw new WirePermissionException('The linked user cannot view this type of financial record.');
        }
        $modules = $this->wire()->modules;
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');
        $repository = $kind === 'invoice'
            ? $modules->get('KontorInvoices')->invoiceRepository()
            : $modules->get('KontorPayments')->paymentRepository();

        return [$repository, $organization->uid->toString()];
    }

    private function mcpFinanceRecord(Invoice|Payment $record): array
    {
        $summary = ['uid' => $record->uid->toString(), 'number' => $record->number, 'status' => $record->status];
        if ($record instanceof Invoice) {
            return $summary + [
                'kind' => $record->kind,
                'currency' => $record->currencyCode,
                'total_minor' => $record->total->amountMinor(),
                'paid_minor' => $record->paid->amountMinor(),
                'due_minor' => $record->due->amountMinor(),
                'issue_date' => $record->issueDate?->format('Y-m-d'),
                'due_date' => $record->dueDate?->format('Y-m-d'),
            ];
        }

        return $summary + [
            'currency' => $record->amount->currencyCode(),
            'amount_minor' => $record->amount->amountMinor(),
            'payment_date' => $record->paymentDate?->format('Y-m-d'),
            'method' => $record->method,
        ];
    }
}
