<?php

declare(strict_types=1);

namespace Kontor\Germany\Application;

/**
 * Validates a German USt-IdNr. (VAT identification number): the format
 * `DE` followed by 9 digits, the last of which is a check digit computed
 * with the published ISO 7064 MOD 11,10-style algorithm the BZSt
 * (Bundeszentralamt für Steuern) itself uses. This checks the number's
 * own internal consistency only — not whether it's actually registered
 * with the tax authority, which would require an external lookup (e.g.
 * the EU VIES service) this class deliberately doesn't perform.
 */
final class GermanTaxIdValidator
{
    public function isValid(string $taxId): bool
    {
        $normalized = strtoupper(str_replace([' ', '-'], '', $taxId));

        if (!preg_match('/^DE(\d{9})$/', $normalized, $matches)) {
            return false;
        }

        $digits = array_map('intval', str_split($matches[1]));
        $checkDigit = array_pop($digits);

        $product = 10;

        foreach ($digits as $digit) {
            $sum = ($digit + $product) % 10;

            if ($sum === 0) {
                $sum = 10;
            }

            $product = (2 * $sum) % 11;
        }

        $computedCheckDigit = 11 - $product;

        if ($computedCheckDigit === 10) {
            $computedCheckDigit = 0;
        }

        return $computedCheckDigit === $checkDigit;
    }
}
