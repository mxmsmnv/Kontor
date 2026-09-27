<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

/**
 * The "localization contracts" milestone (kontor.md Substage 9.4). A
 * country package (kontor.md#5.4 — KontorGermany, KontorUSA, …)
 * registers one implementation as a capability in Core's
 * `CapabilityRegistry` (`capability: "localization.{countryCode}"`, e.g.
 * `"localization.de"`), the same inverted-dependency shape
 * `kontor/cache`/`kontor/files` already use for their own capabilities —
 * never a hard dependency from Core or a business package onto a
 * specific country's implementation.
 */
interface LocalizationProviderInterface
{
    /**
     * ISO 3166-1 alpha-2, upper case (e.g. "DE").
     */
    public function countryCode(): string;

    /**
     * Validates a national tax identifier's own format/checksum rules
     * (e.g. a German USt-IdNr.) — not whether it's actually registered
     * with a tax authority, which would require an external lookup this
     * contract deliberately doesn't require.
     */
    public function validateTaxId(string $taxId): bool;

    /**
     * @return string[] document format keys this country package can
     *                   produce (e.g. "xrechnung", "zugferd")
     */
    public function supportedDocumentFormats(): array;
}
