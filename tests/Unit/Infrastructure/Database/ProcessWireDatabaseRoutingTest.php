<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Database;

use PHPUnit\Framework\TestCase;

final class ProcessWireDatabaseRoutingTest extends TestCase
{
    public function testRuntimeModulesDoNotBypassWireDatabaseTranslator(): void
    {
        $root = dirname(__DIR__, 4);
        $packageModules = glob($root . '/packages/*/*.module.php') ?: [];
        $files = [
            $root . '/Kontor.module.php',
            $root . '/ProcessKontor.module.php',
            ...$packageModules,
        ];

        foreach($files as $file) {
            $source = (string) file_get_contents($file);
            self::assertStringNotContainsString(
                '->database->pdo()',
                $source,
                basename($file) . ' bypasses WireDatabasePDO translation.'
            );
        }
    }
}
