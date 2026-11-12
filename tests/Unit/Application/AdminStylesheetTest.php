<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;

final class AdminStylesheetTest extends TestCase
{
    public function test_primary_button_links_preserve_the_theme_foreground(): void
    {
        $stylesheet = file_get_contents(__DIR__ . '/../../../assets/kontor.admin.css');

        self::assertIsString($stylesheet);
        self::assertMatchesRegularExpression(
            '/\.kontor-shell\s+\.uk-button-primary\.uk-link-reset\s*\{[^}]*color:\s*var\(--pw-button-color,\s*#fff\)\s*!important;/s',
            $stylesheet,
        );
    }
}
