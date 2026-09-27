<?php

declare(strict_types=1);

namespace Kontor\Sales\Application;

use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Sales\Domain\Quotation;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;
use RuntimeException;

/**
 * Substage 4.1 "status workflows" for quotations (kontor.md diagram 17.1:
 * "Prepare quotation -> Accepted? -> No: Revise, expire or reject").
 * Issuing needs a real document number (kontor.md#15.1's `number` column),
 * so it's not a pure domain method — it depends on SequenceService.
 */
final class QuotationWorkflowService
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly DocumentLineRepository $lines,
        private readonly SequenceService $sequences,
    ) {
    }

    public function issue(string $quotationUid): Quotation
    {
        $quotation = $this->quotations->require($quotationUid);

        if (!$quotation->isDraft()) {
            throw new RuntimeException("Quotation \"{$quotationUid}\" is not a draft and cannot be issued.");
        }

        $quotation->applyTotalsFromLines($this->lines->forDocument('quotation', $quotationUid));
        $quotation->number = $this->sequences->next(
            $quotation->organizationId, 'sales', 'quotation', prefix: 'QUO-', padding: 5, resetPolicy: 'yearly'
        );
        $quotation->issueDate ??= new \DateTimeImmutable();
        $quotation->issuedAt = new \DateTimeImmutable();
        $quotation->status = 'issued';

        $this->quotations->save($quotation);

        return $quotation;
    }

    public function send(string $quotationUid): Quotation
    {
        $quotation = $this->quotations->require($quotationUid);

        if ($quotation->status !== 'issued' && $quotation->status !== 'sent') {
            throw new RuntimeException("Quotation \"{$quotationUid}\" must be issued before it can be sent.");
        }

        $quotation->status = 'sent';
        $this->quotations->save($quotation);

        return $quotation;
    }

    public function accept(string $quotationUid): Quotation
    {
        $quotation = $this->quotations->require($quotationUid);

        if (!$quotation->isOpen()) {
            throw new RuntimeException("Quotation \"{$quotationUid}\" must be issued or sent before it can be accepted.");
        }

        $quotation->status = 'accepted';
        $quotation->acceptedAt = new \DateTimeImmutable();
        $this->quotations->save($quotation);

        return $quotation;
    }

    public function reject(string $quotationUid): Quotation
    {
        $quotation = $this->quotations->require($quotationUid);

        if (!$quotation->isOpen()) {
            throw new RuntimeException("Quotation \"{$quotationUid}\" must be issued or sent before it can be rejected.");
        }

        $quotation->status = 'rejected';
        $quotation->rejectedAt = new \DateTimeImmutable();
        $this->quotations->save($quotation);

        return $quotation;
    }

    public function expire(string $quotationUid): Quotation
    {
        $quotation = $this->quotations->require($quotationUid);

        if (!$quotation->isOpen()) {
            throw new RuntimeException("Quotation \"{$quotationUid}\" must be issued or sent before it can expire.");
        }

        $quotation->status = 'expired';
        $this->quotations->save($quotation);

        return $quotation;
    }

    public function cancel(string $quotationUid): Quotation
    {
        $quotation = $this->quotations->require($quotationUid);

        if ($quotation->isClosed()) {
            throw new RuntimeException("Quotation \"{$quotationUid}\" is already closed and cannot be cancelled.");
        }

        $quotation->status = 'cancelled';
        $this->quotations->save($quotation);

        return $quotation;
    }
}
