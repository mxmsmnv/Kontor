<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Application\QuotationToOrderConversionService;
use Kontor\Sales\Application\QuotationWorkflowService;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;

final class QuotationToOrderConversionServiceTest extends DatabaseTestCase
{
    private QuotationRepository $quotations;
    private OrderRepository $orders;
    private DocumentLineRepository $lines;
    private QuotationWorkflowService $quotationWorkflow;
    private QuotationToOrderConversionService $conversion;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $sequences = new SequenceService($this->pdo, $organizations);

        $this->quotations = new QuotationRepository($this->pdo, $organizations);
        $this->orders = new OrderRepository($this->pdo, $organizations);
        $this->lines = new DocumentLineRepository($this->pdo, $organizations);
        $this->quotationWorkflow = new QuotationWorkflowService($this->quotations, $this->lines, $sequences);
        $this->conversion = new QuotationToOrderConversionService($this->quotations, $this->orders, $this->lines, $sequences);
    }

    private function acceptedQuotationWithLines(): Quotation
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);

        $this->lines->save(DocumentLine::create(
            $this->organizationUid, 'quotation', $quotation->uid->toString(), 'Widget', 2.0, Money::ofMinor(1000, 'EUR'), taxRate: 20.0,
        ));
        $this->lines->save(DocumentLine::create(
            $this->organizationUid, 'quotation', $quotation->uid->toString(), 'Gadget', 1.0, Money::ofMinor(500, 'EUR'), taxRate: 20.0,
        ));

        $this->quotationWorkflow->issue($quotation->uid->toString());
        $this->quotationWorkflow->accept($quotation->uid->toString());

        return $this->quotations->require($quotation->uid->toString());
    }

    public function test_converts_an_accepted_quotation_into_an_order_with_copied_lines_and_totals(): void
    {
        $quotation = $this->acceptedQuotationWithLines();

        $order = $this->conversion->convert($quotation->uid->toString());

        $this->assertSame($quotation->uid->toString(), $order->quotationUid);
        $this->assertSame('ct_01', $order->customerUid);
        $this->assertStringStartsWith('SO-', $order->number);
        $this->assertSame('pending', $order->orderStatus);

        // subtotal = 2000 + 500 = 2500; tax = 400 + 100 = 500; total = 3000 (+0 shipping)
        $this->assertSame(2500, $order->subtotal->amountMinor());
        $this->assertSame(500, $order->tax->amountMinor());
        $this->assertSame(3000, $order->total->amountMinor());

        $orderLines = $this->lines->forDocument('order', $order->uid->toString());
        $this->assertCount(2, $orderLines);
        $this->assertSame(['Widget', 'Gadget'], array_map(fn ($l) => $l->title, $orderLines));

        // original quotation lines are untouched
        $this->assertCount(2, $this->lines->forDocument('quotation', $quotation->uid->toString()));
    }

    public function test_refuses_to_convert_a_quotation_that_is_not_accepted(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must be accepted');

        $this->conversion->convert($quotation->uid->toString());
    }

    public function test_refuses_a_draft_quotation_even_with_lines(): void
    {
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $this->quotations->save($quotation);
        $this->lines->save(DocumentLine::create($this->organizationUid, 'quotation', $quotation->uid->toString(), 'Widget', 1.0, Money::ofMinor(1000, 'EUR')));

        $this->expectException(\RuntimeException::class);

        $this->conversion->convert($quotation->uid->toString());
    }

    public function test_refuses_to_convert_the_same_quotation_twice(): void
    {
        $quotation = $this->acceptedQuotationWithLines();
        $this->conversion->convert($quotation->uid->toString());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('already converted');

        $this->conversion->convert($quotation->uid->toString());
    }
}
