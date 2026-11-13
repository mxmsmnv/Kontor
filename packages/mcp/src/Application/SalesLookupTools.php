<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Order;
use Kontor\Sales\Domain\Quotation;
use ProcessWire\KontorSales;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait SalesLookupTools
{
    private const SALES_MCP_STATUSES = [
        'quotation' => ['draft', 'issued', 'sent', 'accepted', 'rejected', 'expired', 'cancelled'],
        'order' => ['pending', 'confirmed', 'completed', 'cancelled'],
    ];

    private function salesLookupTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorSales')) {
            return [];
        }

        $tools = [];
        foreach (self::SALES_MCP_STATUSES as $kind => $statuses) {
            $tools[] = $this->tool(
                'kontor_sales_' . $kind . 's_list',
                'List sales ' . $kind . 's',
                'Find active ' . $kind . 's by number or filter by status in the Workspace organization. Pass an exact customer UID only if you can view that customer type. To find orders for invoice drafts, filter for confirmed or completed.',
                $kind === 'quotation' ? $this->mcpSalesQuotationsList(...) : $this->mcpSalesOrdersList(...),
                'read',
                [
                    'query' => ['type' => 'string', 'maxLength' => 120],
                    'status' => ['type' => 'string', 'enum' => $statuses],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                    'customer_type' => ['type' => 'string', 'enum' => ['contact', 'company']],
                    'customer_uid' => $this->uidSchema(),
                ],
            );
            $tools[] = $this->tool(
                'kontor_sales_' . $kind . '_get',
                'Read a sales ' . $kind,
                'Read the selected ' . $kind . ' and up to 25 lines in the Workspace organization.',
                $kind === 'quotation' ? $this->mcpSalesQuotationGet(...) : $this->mcpSalesOrderGet(...),
                'read',
                ['uid' => $this->uidSchema()],
                ['uid'],
            );
        }
        return $tools;
    }

    public function mcpSalesQuotationsList(
        string $query = '',
        ?string $status = null,
        int $limit = 25,
        int $offset = 0,
        ?string $customer_type = null,
        ?string $customer_uid = null,
    ): array {
        return $this->listSalesMcpRecords('quotation', $query, $status, $limit, $offset, $customer_type, $customer_uid);
    }

    public function mcpSalesOrdersList(
        string $query = '',
        ?string $status = null,
        int $limit = 25,
        int $offset = 0,
        ?string $customer_type = null,
        ?string $customer_uid = null,
    ): array {
        return $this->listSalesMcpRecords('order', $query, $status, $limit, $offset, $customer_type, $customer_uid);
    }

    public function mcpSalesQuotationGet(string $uid): array
    {
        return $this->getSalesMcpRecord('quotation', $uid);
    }

    public function mcpSalesOrderGet(string $uid): array
    {
        return $this->getSalesMcpRecord('order', $uid);
    }

    private function listSalesMcpRecords(
        string $kind,
        string $query,
        ?string $status,
        int $limit,
        int $offset,
        ?string $customerType,
        ?string $customerUid,
    ): array {
        [$actor, $sales, $organization] = $this->salesMcpContext($kind);
        if ($limit < 1 || $limit > 50 || $offset < 0 || $offset > 10000 || mb_strlen($query) > 120
            || ($status !== null && !in_array($status, self::SALES_MCP_STATUSES[$kind], true))) {
            throw new WireException('Invalid sales query, status or pagination.');
        }
        if (($customerType === null) !== ($customerUid === null)) {
            throw new WireException('Provide both customer type and UID for customer filtering.');
        }
        if ($customerType !== null) {
            if (!in_array($customerType, ['contact', 'company'], true)) {
                throw new WireException('Invalid sales customer type.');
            }
            if (!$this->salesMcpCan($actor, 'kontor-contacts-' . $customerType . '-view')) {
                throw new WirePermissionException('The linked user cannot view this customer type.');
            }
            $this->salesMcpValidateUid($customerUid);
        }
        $repository = $kind === 'order' ? $sales->orderRepository() : $sales->quotationRepository();
        $records = $repository->findMatching(
            $organization, trim($query), $status, limit: $limit + 1, offset: $offset,
            numberOnly: true, customerType: $customerType, customerUid: $customerUid,
        );

        return [
            'items' => array_map(fn (Order|Quotation $row): array => $this->presentSalesMcpRecord($row, $actor), array_slice($records, 0, $limit)),
            'next_offset' => count($records) > $limit && $offset + $limit <= 10000 ? $offset + $limit : null,
        ];
    }

    private function getSalesMcpRecord(string $kind, string $uid): array
    {
        [$actor, $sales, $organization] = $this->salesMcpContext($kind);
        $this->salesMcpValidateUid($uid);
        $repository = $kind === 'order' ? $sales->orderRepository() : $sales->quotationRepository();
        $record = $repository->find($uid, includeArchived: false);
        if ($record === null || $record->organizationId !== $organization) {
            throw new Wire404Exception('The sales record was not found.');
        }

        $lines = $sales->documentLineRepository()->forDocument($kind, $uid, $organization, 26);
        return [
            'record' => $this->presentSalesMcpRecord($record, $actor),
            'lines' => array_map(static fn (DocumentLine $line): array => [
                'title' => mb_substr($line->title, 0, 255),
                'description' => $line->description === null ? null : mb_substr($line->description, 0, 500),
                'quantity' => $line->quantity,
                'unit_code' => $line->unitCode,
                'unit_price_minor' => $line->unitPrice->amountMinor(),
                'currency' => $line->unitPrice->currencyCode(),
                'tax_rate' => $line->taxRate,
                'total_minor' => $line->total()->amountMinor(),
            ], array_slice($lines, 0, 25)),
            'more_lines' => count($lines) > 25,
        ];
    }

    /** @return array{User,KontorSales,string} */
    private function salesMcpContext(string $kind): array
    {
        $actor = $this->mcpActor();
        if ($actor === null || !$this->salesMcpCan($actor, 'kontor-sales-' . $kind . '-view')) {
            throw new WirePermissionException('The linked user cannot view this sales record type.');
        }
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorSales')) {
            throw new Wire404Exception('Kontor Sales is unavailable.');
        }
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');
        return [$actor, $modules->get('KontorSales'), $organization->uid->toString()];
    }

    private function salesMcpValidateUid(string $uid): void
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('Sales record and customer UIDs must be valid ULIDs.');
        }
    }

    private function salesMcpCan(User $actor, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission($permission);
    }

    private function presentSalesMcpRecord(Order|Quotation $record, User $actor): array
    {
        $result = [
            'uid' => $record->uid->toString(),
            'number' => $record->number,
            'currency' => $record->currencyCode,
            'subtotal_minor' => $record->subtotal->amountMinor(),
            'tax_minor' => $record->tax->amountMinor(),
            'total_minor' => $record->total->amountMinor(),
            'issue_date' => $record->issueDate?->format('Y-m-d'),
        ];
        if (in_array($record->customerType, ['contact', 'company'], true)
            && $this->salesMcpCan($actor, 'kontor-contacts-' . $record->customerType . '-view')) {
            $result['customer_type'] = $record->customerType;
            $result['customer_uid'] = $record->customerUid;
        }
        if ($record instanceof Order) {
            return $result + [
                'status' => $record->orderStatus,
                'fulfillment_status' => $record->fulfillmentStatus,
                'expected_delivery_date' => $record->expectedDeliveryDate?->format('Y-m-d'),
                'quotation_uid' => $this->salesMcpCan($actor, 'kontor-sales-quotation-view') ? $record->quotationUid : null,
            ];
        }
        return $result + [
            'status' => $record->status,
            'language' => $record->documentLanguage,
            'valid_until' => $record->validUntil?->format('Y-m-d'),
            'deal_uid' => $this->salesMcpCan($actor, 'kontor-crm-deal-view') ? $record->dealUid : null,
        ];
    }
}
