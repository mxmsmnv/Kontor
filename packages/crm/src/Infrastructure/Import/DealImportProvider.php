<?php

declare(strict_types=1);

namespace Kontor\CRM\Infrastructure\Import;

use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\SDK\Contracts\ImportProviderInterface;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;
use Kontor\SDK\ValueObjects\Money;

final class DealImportProvider implements ImportProviderInterface
{
    public function __construct(private readonly DealRepository $deals)
    {
    }

    public function entityType(): string
    {
        return 'deal';
    }

    public function fields(): array
    {
        return ['title', 'pipeline_uid', 'stage_uid', 'contact_uid', 'company_uid', 'value_minor', 'currency_code'];
    }

    public function validate(array $record, ImportContext $context): ValidationResult
    {
        $errors = [];

        if ($this->stringOrNull($record['title'] ?? null) === null) {
            $errors['title'][] = 'deal.title.required';
        }

        if ($this->stringOrNull($record['pipeline_uid'] ?? null) === null) {
            $errors['pipeline_uid'][] = 'deal.pipeline_uid.required';
        }

        if ($this->stringOrNull($record['stage_uid'] ?? null) === null) {
            $errors['stage_uid'][] = 'deal.stage_uid.required';
        }

        $minor = $record['value_minor'] ?? null;
        $currency = $this->stringOrNull($record['currency_code'] ?? null);

        if (($minor !== null && $minor !== '') !== ($currency !== null)) {
            $errors['value_minor'][] = 'deal.value.currency_required';
        }

        return $errors === [] ? ValidationResult::valid() : ValidationResult::invalid($errors);
    }

    public function findExisting(array $record, ImportContext $context): ?string
    {
        return null;
    }

    public function import(array $record, ImportContext $context): ImportRecordResult
    {
        $minor = $record['value_minor'] ?? null;
        $currency = $this->stringOrNull($record['currency_code'] ?? null);

        $deal = Deal::create(
            organizationId: $context->organizationId,
            pipelineUid: $record['pipeline_uid'],
            stageUid: $record['stage_uid'],
            title: $record['title'],
            contactUid: $this->stringOrNull($record['contact_uid'] ?? null),
            companyUid: $this->stringOrNull($record['company_uid'] ?? null),
            value: ($minor !== null && $minor !== '' && $currency !== null) ? Money::ofMinor((int) $minor, $currency) : null,
        );

        $this->deals->save($deal);

        return new ImportRecordResult('created', $deal->uid->toString());
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
