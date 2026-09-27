<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Application\QuotationWorkflowService;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;

final class QuotationWorkflowServiceTest extends DatabaseTestCase
{
    private QuotationRepository $quotations;
    private DocumentLineRepository $lines;
    private QuotationWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->quotations = new QuotationRepository($this->pdo, $organizations);
        $this->lines = new DocumentLineRepository($this->pdo, $organizations);
        $this->service = new QuotationWorkflowService($this->quotations, $this->lines, new SequenceService($this->pdo, $organizations));
    }

    public function test_issue_assigns_a_number_and_recomputes_totals(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);
        $this->lines->save(DocumentLine::create($this->organizationUid, 'quotation', $quotation->uid->toString(), 'Widget', 2.0, Money::ofMinor(1000, 'EUR')));

        $issued = $this->service->issue($quotation->uid->toString());

        $this->assertSame('issued', $issued->status);
        $this->assertStringStartsWith('QUO-', $issued->number);
        $this->assertNotNull($issued->issuedAt);
        $this->assertSame(2000, $issued->total->amountMinor());
    }

    public function test_issue_refuses_a_non_draft_quotation(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);
        $this->service->issue($quotation->uid->toString());

        $this->expectException(\RuntimeException::class);

        $this->service->issue($quotation->uid->toString());
    }

    public function test_send_requires_issued_first(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);

        $this->expectException(\RuntimeException::class);

        $this->service->send($quotation->uid->toString());
    }

    public function test_full_lifecycle_issue_send_accept(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);

        $this->service->issue($quotation->uid->toString());
        $this->service->send($quotation->uid->toString());
        $accepted = $this->service->accept($quotation->uid->toString());

        $this->assertSame('accepted', $accepted->status);
        $this->assertNotNull($accepted->acceptedAt);
    }

    public function test_reject_records_the_rejection_time(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);
        $this->service->issue($quotation->uid->toString());

        $rejected = $this->service->reject($quotation->uid->toString());

        $this->assertSame('rejected', $rejected->status);
        $this->assertNotNull($rejected->rejectedAt);
    }

    public function test_cancel_refuses_an_already_closed_quotation(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);
        $this->service->issue($quotation->uid->toString());
        $this->service->accept($quotation->uid->toString());

        $this->expectException(\RuntimeException::class);

        $this->service->cancel($quotation->uid->toString());
    }

    public function test_cancel_a_draft_quotation(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);

        $cancelled = $this->service->cancel($quotation->uid->toString());

        $this->assertSame('cancelled', $cancelled->status);
    }

    public function test_expire_requires_an_open_quotation(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);
        $this->service->issue($quotation->uid->toString());

        $expired = $this->service->expire($quotation->uid->toString());

        $this->assertSame('expired', $expired->status);
    }

    public function test_sequential_issues_get_incrementing_numbers(): void
    {
        $first = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $second = Quotation::create($this->organizationUid, 'contact', 'ct_02', 'EUR');
        $this->quotations->save($first);
        $this->quotations->save($second);

        $issuedFirst = $this->service->issue($first->uid->toString());
        $issuedSecond = $this->service->issue($second->uid->toString());

        $this->assertNotSame($issuedFirst->number, $issuedSecond->number);
    }
}
