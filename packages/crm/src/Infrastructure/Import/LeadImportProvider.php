<?php

declare(strict_types=1);

namespace Kontor\CRM\Infrastructure\Import;

use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\SDK\Contracts\ImportProviderInterface;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;
use Kontor\SDK\ValueObjects\Money;

final class LeadImportProvider implements ImportProviderInterface
{
    public function __construct(private readonly LeadRepository $leads)
    {
    }

    public function entityType(): string
    {
        return 'lead';
    }

    public function fields(): array
    {
        return ['title', 'contact_uid', 'company_uid', 'source', 'priority', 'estimated_value_minor', 'currency_code'];
    }

    public function validate(array $record, ImportContext $context): ValidationResult
    {
        $title = $this->stringOrNull($record['title'] ?? null);

        if ($title === null) {
            return ValidationResult::invalid(['title' => ['lead.title.required']]);
        }

        $minor = $record['estimated_value_minor'] ?? null;
        $currency = $this->stringOrNull($record['currency_code'] ?? null);

        if (($minor !== null && $minor !== '') !== ($currency !== null)) {
            return ValidationResult::invalid(['estimated_value_minor' => ['lead.estimated_value.currency_required']]);
        }

        return ValidationResult::valid();
    }

    public function findExisting(array $record, ImportContext $context): ?string
    {
        return null;
    }

    public function import(array $record, ImportContext $context): ImportRecordResult
    {
        $minor = $record['estimated_value_minor'] ?? null;
        $currency = $this->stringOrNull($record['currency_code'] ?? null);

        $lead = Lead::create(
            organizationId: $context->organizationId,
            title: $record['title'],
            contactUid: $this->stringOrNull($record['contact_uid'] ?? null),
            companyUid: $this->stringOrNull($record['company_uid'] ?? null),
            source: $this->stringOrNull($record['source'] ?? null),
            priority: $record['priority'] ?? 'medium',
            estimatedValue: ($minor !== null && $minor !== '' && $currency !== null) ? Money::ofMinor((int) $minor, $currency) : null,
        );

        $this->leads->save($lead);

        return new ImportRecordResult('created', $lead->uid->toString());
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
