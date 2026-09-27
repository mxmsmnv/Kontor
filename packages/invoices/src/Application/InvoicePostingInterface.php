<?php

declare(strict_types=1);

namespace Kontor\Invoices\Application;

use Kontor\Invoices\Domain\Invoice;

/**
 * Optional accounting bridge for issued and cancelled billing documents.
 *
 * Invoices remains usable without Ledger; the ProcessWire module supplies
 * the Ledger implementation when that component is installed.
 */
interface InvoicePostingInterface
{
    public function assertCanPost(Invoice $invoice): void;

    public function postIssue(Invoice $invoice, ?int $createdBy = null): void;

    public function postCancellation(Invoice $invoice, ?int $createdBy = null): void;
}
