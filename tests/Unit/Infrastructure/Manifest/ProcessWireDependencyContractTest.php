<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Manifest;

use PHPUnit\Framework\TestCase;

final class ProcessWireDependencyContractTest extends TestCase
{
    public function test_processwire_dependencies_are_declared_as_composer_runtime_dependencies(): void
    {
        $root = dirname(__DIR__, 4);
        $modulePackages = ['Kontor' => 'kontor/core'];

        foreach (glob($root . '/packages/*/kontor.json') ?: [] as $manifestPath) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            $modulePackages[$manifest['processWireModule']] = $manifest['package'];
        }

        $missing = [];

        foreach (glob($root . '/packages/*/*.module.php') ?: [] as $modulePath) {
            $source = (string) file_get_contents($modulePath);
            self::assertSame(1, preg_match('/class\s+(Kontor\w+)/', $source, $classMatch), $modulePath);
            self::assertSame(
                1,
                preg_match('/[\'\"]requires[\'\"]\s*=>\s*\[([^]]*)\]/s', $source, $requiresMatch),
                $modulePath,
            );

            $composerPath = dirname($modulePath) . '/composer.json';
            $composer = json_decode((string) file_get_contents($composerPath), true, flags: JSON_THROW_ON_ERROR);
            preg_match_all('/[\'\"](Kontor\w+)[\'\"]/', $requiresMatch[1], $dependencyMatches);

            foreach ($dependencyMatches[1] as $dependencyModule) {
                $package = $modulePackages[$dependencyModule] ?? null;

                if ($package !== null && !array_key_exists($package, $composer['require'] ?? [])) {
                    $missing[] = sprintf('%s requires %s (%s)', $classMatch[1], $dependencyModule, $package);
                }
            }
        }

        self::assertSame([], $missing, implode("\n", $missing));
    }
}
