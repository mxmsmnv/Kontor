<?php

declare(strict_types=1);

namespace Kontor\Sales\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Sales\Domain\Order;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#15.2
 */
final class OrderRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Order
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_sales_orders WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Order
    {
        return $this->find($id) ?? throw new RuntimeException("Order \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Order) {
            throw new InvalidArgumentException('OrderRepository::save() expects an Order.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_sales_orders
                (uid, organization_id, number, customer_type, customer_uid, contact_uid, quotation_uid, issue_date,
                 expected_delivery_date, currency_code, subtotal_minor, discount_minor, tax_minor, shipping_minor,
                 total_minor, order_status, payment_status, fulfillment_status, confirmed_at, completed_at,
                 created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :number, :customer_type, :customer_uid, :contact_uid, :quotation_uid, :issue_date,
                 :expected_delivery_date, :currency_code, :subtotal_minor, :discount_minor, :tax_minor, :shipping_minor,
                 :total_minor, :order_status, :payment_status, :fulfillment_status, :confirmed_at, :completed_at,
                 :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                number = VALUES(number), customer_type = VALUES(customer_type), customer_uid = VALUES(customer_uid),
                contact_uid = VALUES(contact_uid), quotation_uid = VALUES(quotation_uid), issue_date = VALUES(issue_date),
                expected_delivery_date = VALUES(expected_delivery_date), currency_code = VALUES(currency_code),
                subtotal_minor = VALUES(subtotal_minor), discount_minor = VALUES(discount_minor),
                tax_minor = VALUES(tax_minor), shipping_minor = VALUES(shipping_minor), total_minor = VALUES(total_minor),
                order_status = VALUES(order_status), payment_status = VALUES(payment_status),
                fulfillment_status = VALUES(fulfillment_status), confirmed_at = VALUES(confirmed_at),
                completed_at = VALUES(completed_at), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'number' => $entity->number,
            'customer_type' => $entity->customerType,
            'customer_uid' => $entity->customerUid,
            'contact_uid' => $entity->contactUid,
            'quotation_uid' => $entity->quotationUid,
            'issue_date' => $entity->issueDate?->format('Y-m-d'),
            'expected_delivery_date' => $entity->expectedDeliveryDate?->format('Y-m-d'),
            'currency_code' => $entity->currencyCode,
            'subtotal_minor' => $entity->subtotal->amountMinor(),
            'discount_minor' => $entity->discount->amountMinor(),
            'tax_minor' => $entity->tax->amountMinor(),
            'shipping_minor' => $entity->shipping->amountMinor(),
            'total_minor' => $entity->total->amountMinor(),
            'order_status' => $entity->orderStatus,
            'payment_status' => $entity->paymentStatus,
            'fulfillment_status' => $entity->fulfillmentStatus,
            'confirmed_at' => $entity->confirmedAt?->format('Y-m-d H:i:s.u'),
            'completed_at' => $entity->completedAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_sales_orders SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_sales_orders SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    private function hydrate(array $row): Order
    {
        return new Order(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            number: $row['number'],
            customerType: $row['customer_type'],
            customerUid: $row['customer_uid'],
            contactUid: $row['contact_uid'],
            quotationUid: $row['quotation_uid'],
            issueDate: $row['issue_date'] !== null ? new \DateTimeImmutable($row['issue_date']) : null,
            expectedDeliveryDate: $row['expected_delivery_date'] !== null ? new \DateTimeImmutable($row['expected_delivery_date']) : null,
            currencyCode: $row['currency_code'],
            subtotal: Money::ofMinor((int) $row['subtotal_minor'], $row['currency_code']),
            discount: Money::ofMinor((int) $row['discount_minor'], $row['currency_code']),
            tax: Money::ofMinor((int) $row['tax_minor'], $row['currency_code']),
            shipping: Money::ofMinor((int) $row['shipping_minor'], $row['currency_code']),
            total: Money::ofMinor((int) $row['total_minor'], $row['currency_code']),
            orderStatus: $row['order_status'],
            paymentStatus: $row['payment_status'],
            fulfillmentStatus: $row['fulfillment_status'],
            confirmedAt: $row['confirmed_at'] !== null ? new \DateTimeImmutable($row['confirmed_at']) : null,
            completedAt: $row['completed_at'] !== null ? new \DateTimeImmutable($row['completed_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
