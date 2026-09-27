<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;

final class AdminStylesheetTest extends TestCase
{
    public function test_primary_page_header_actions_override_link_reset_text_color(): void
    {
        $stylesheet = file_get_contents(__DIR__ . '/../../../assets/kontor.admin.css');

        self::assertIsString($stylesheet);
        self::assertMatchesRegularExpression(
            '/\.kontor-pagehead__actions\s+\.uk-button-primary\s*\{[^}]*color:\s*#fff\s*!important;/s',
            $stylesheet,
        );
    }
}
