<?php

declare(strict_types=1);

namespace Kontor\Germany\Application;

use Kontor\SDK\Contracts\LocalizationProviderInterface;

/**
 * The "Germany package" milestone: registered as the
 * `"localization.de"` capability in Core's `CapabilityRegistry` (see
 * `KontorGermany::init()`).
 */
final class GermanyLocalizationProvider implements LocalizationProviderInterface
{
    public function __construct(
        private readonly GermanTaxIdValidator $taxIdValidator = new GermanTaxIdValidator(),
    ) {
    }

    public function countryCode(): string
    {
        return 'DE';
    }

    public function validateTaxId(string $taxId): bool
    {
        return $this->taxIdValidator->isValid($taxId);
    }

    public function supportedDocumentFormats(): array
    {
        return ['xrechnung', 'zugferd'];
    }
}
