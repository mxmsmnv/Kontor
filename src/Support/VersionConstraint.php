<?php

declare(strict_types=1);

namespace Kontor\Core\Support;

/**
 * Minimal Composer-style constraint matching shared by the capability
 * registry and the component dependency checker. Supports the subset of
 * syntax Kontor manifests actually use (kontor.md#22.1): "^1.0" caret
 * ranges, ">=8.2" comparisons, and exact versions.
 */
final class VersionConstraint
{
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if (str_starts_with($constraint, '^')) {
            return self::satisfiesCaret($version, ltrim($constraint, '^'));
        }

        foreach (['>=', '<=', '>', '<', '=='] as $operator) {
            if (str_starts_with($constraint, $operator)) {
                $required = trim(substr($constraint, strlen($operator)));

                return version_compare($version, $required, $operator === '==' ? '=' : $operator);
            }
        }

        return $version === $constraint;
    }

    private static function satisfiesCaret(string $version, string $required): bool
    {
        $requiredMajor = explode('.', $required)[0];
        $actualMajor = explode('.', $version)[0];

        return $requiredMajor === $actualMajor && version_compare($version, $required, '>=');
    }
}
