<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\Import;

use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\SDK\Contracts\ImportProviderInterface;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;

final class CompanyImportProvider implements ImportProviderInterface
{
    public function __construct(private readonly CompanyRepository $companies)
    {
    }

    public function entityType(): string
    {
        return 'company';
    }

    public function fields(): array
    {
        return ['legal_name', 'trading_name', 'registration_number', 'tax_number', 'vat_number', 'website', 'email', 'phone'];
    }

    public function validate(array $record, ImportContext $context): ValidationResult
    {
        $legalName = $this->stringOrNull($record['legal_name'] ?? null);

        if ($legalName === null) {
            return ValidationResult::invalid(['legal_name' => ['company.legal_name.required']]);
        }

        $email = $this->stringOrNull($record['email'] ?? null);

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ValidationResult::invalid(['email' => ['company.email.invalid']]);
        }

        return ValidationResult::valid();
    }

    public function findExisting(array $record, ImportContext $context): ?string
    {
        $vatNumber = $this->stringOrNull($record['vat_number'] ?? null);

        if ($vatNumber !== null) {
            $existing = $this->companies->findByVatNumber($context->organizationId, $vatNumber);

            if ($existing !== null) {
                return $existing->uid->toString();
            }
        }

        $email = $this->stringOrNull($record['email'] ?? null);

        if ($email !== null) {
            $existing = $this->companies->findByEmail($context->organizationId, $email);

            if ($existing !== null) {
                return $existing->uid->toString();
            }
        }

        return null;
    }

    public function import(array $record, ImportContext $context): ImportRecordResult
    {
        $existingUid = $this->findExisting($record, $context);

        if ($existingUid !== null) {
            $company = $this->companies->require($existingUid);
            $this->applyRecord($company, $record);
            $this->companies->save($company);

            return new ImportRecordResult('updated', $existingUid);
        }

        $company = Company::create(
            organizationId: $context->organizationId,
            legalName: $record['legal_name'],
            tradingName: $this->stringOrNull($record['trading_name'] ?? null),
            registrationNumber: $this->stringOrNull($record['registration_number'] ?? null),
            taxNumber: $this->stringOrNull($record['tax_number'] ?? null),
            vatNumber: $this->stringOrNull($record['vat_number'] ?? null),
            website: $this->stringOrNull($record['website'] ?? null),
            email: $this->stringOrNull($record['email'] ?? null),
            phone: $this->stringOrNull($record['phone'] ?? null),
        );

        $this->companies->save($company);

        return new ImportRecordResult('created', $company->uid->toString());
    }

    /**
     * @param array<string, mixed> $record
     */
    private function applyRecord(Company $company, array $record): void
    {
        foreach ([
            'legal_name' => 'legalName', 'trading_name' => 'tradingName',
            'registration_number' => 'registrationNumber', 'tax_number' => 'taxNumber',
            'vat_number' => 'vatNumber', 'website' => 'website', 'email' => 'email', 'phone' => 'phone',
        ] as $field => $property) {
            if (array_key_exists($field, $record)) {
                $company->$property = $this->stringOrNull($record[$field]);
            }
        }
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
