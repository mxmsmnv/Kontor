<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

/**
 * Server-provided translation registry consumed by JavaScript
 * (kontor.md#23). English is always the source and fallback language;
 * PHP-side translation still goes through ProcessWire's own __() system —
 * this registry only serves the JS side.
 */
final class TranslationRegistry
{
    private const FALLBACK_LANGUAGE = 'en';

    /**
     * @var array<string, array<string, array<string, string>>> component => language => key => value
     */
    private array $strings = [];

    /**
     * @param array<string, string> $strings
     */
    public function register(string $component, string $language, array $strings): void
    {
        $this->strings[$component][$language] = array_merge(
            $this->strings[$component][$language] ?? [],
            $strings
        );
    }

    /**
     * Merges every component's strings for the requested language, falling
     * back to English key-by-key where a translation is missing.
     *
     * @return array<string, string>
     */
    public function forLanguage(string $language): array
    {
        $merged = [];

        foreach ($this->strings as $perLanguage) {
            $fallback = $perLanguage[self::FALLBACK_LANGUAGE] ?? [];
            $localized = $perLanguage[$language] ?? [];
            $merged = array_merge($merged, $fallback, $localized);
        }

        return $merged;
    }

    public function toJson(string $language): string
    {
        return json_encode($this->forLanguage($language), JSON_THROW_ON_ERROR);
    }
}
