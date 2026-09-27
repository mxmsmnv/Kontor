<?php

declare(strict_types=1);

namespace Kontor\Germany\DTO;

/**
 * A seller or buyer party, shaped for `XRechnungFormatter` — deliberately
 * decoupled from `kontor/contacts`'/`kontor/invoices`' own domain
 * objects, so this package doesn't need to know their internal
 * representation. Any caller builds one of these from whatever data it
 * already has.
 */
final class LocalizedPartyInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $streetAddress,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly string $countryCode,
        public readonly ?string $taxId = null,
    ) {
    }
}
