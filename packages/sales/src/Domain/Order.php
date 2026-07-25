<?php

declare(strict_types=1);

namespace Kontor\Sales\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#15.2. order_status is actively managed by
 * OrderWorkflowService this substage; payment_status/fulfillment_status
 * are simple descriptive fields here — their real lifecycle belongs to
 * Payments (Substage 4.4) and Inventory (Substage 6.1), not built yet.
 */
final class Order
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public ?string $number,
        public string $customerType,
        public string $customerUid,
        public ?string $contactUid,
        public ?string $quotationUid,
        public ?\DateTimeImmutable $issueDate,
        public ?\DateTimeImmutable $expectedDeliveryDate,
        public string $currencyCode,
        public Money $subtotal,
        public Money $discount,
        public Money $tax,
        public Money $shipping,
        public Money $total,
        public string $orderStatus,
        public string $paymentStatus,
        public string $fulfillmentStatus,
        public ?\DateTimeImmutable $confirmedAt,
        public ?\DateTimeImmutable $completedAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $customerType,
        string $customerUid,
        string $currencyCode,
        ?string $contactUid = null,
        ?string $quotationUid = null,
        ?\DateTimeImmutable $expectedDeliveryDate = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            number: null,
            customerType: $customerType,
            customerUid: $customerUid,
            contactUid: $contactUid,
            quotationUid: $quotationUid,
            issueDate: new \DateTimeImmutable(),
            expectedDeliveryDate: $expectedDeliveryDate,
            currencyCode: strtoupper($currencyCode),
            subtotal: Money::zero($currencyCode),
            discount: Money::zero($currencyCode),
            tax: Money::zero($currencyCode),
            shipping: Money::zero($currencyCode),
            total: Money::zero($currencyCode),
            orderStatus: 'pending',
            paymentStatus: 'unpaid',
            fulfillmentStatus: 'pending',
            confirmedAt: null,
            completedAt: null,
        );
    }

    public function isPending(): bool
    {
        return $this->orderStatus === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->orderStatus === 'confirmed';
    }

    public function isOpen(): bool
    {
        return in_array($this->orderStatus, ['pending', 'confirmed'], true);
    }

    /**
     * @param DocumentLine[] $lines
     */
    public function applyTotalsFromLines(array $lines): void
    {
        $subtotal = Money::zero($this->currencyCode);
        $tax = Money::zero($this->currencyCode);

        foreach ($lines as $line) {
            $subtotal = $subtotal->add($line->subtotal());
            $tax = $tax->add($line->taxAmount());
        }

        $this->subtotal = $subtotal;
        $this->tax = $tax;
        $this->total = $subtotal->add($tax)->add($this->shipping);
    }
}
