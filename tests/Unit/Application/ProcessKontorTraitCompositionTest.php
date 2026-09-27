<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;

final class ProcessKontorTraitCompositionTest extends TestCase
{
    public function test_admin_controller_remains_a_thin_trait_composition_root(): void
    {
        $root = __DIR__ . '/../../..';
        $controller = file_get_contents($root . '/ProcessKontor.module.php');
        self::assertIsString($controller);

        $traits = glob($root . '/src/ProcessKontor/Traits/*.php') ?: [];
        self::assertCount(15, $traits);
        self::assertLessThan(600, substr_count($controller, "\n"));
        self::assertStringNotContainsString('function ___execute', $controller);

        foreach ($traits as $trait) {
            $name = basename($trait, '.php');
            self::assertStringContainsString("require_once __DIR__ . '/src/ProcessKontor/Traits/{$name}.php';", $controller);
            self::assertStringContainsString("use {$name};", $controller);
        }
    }
}
