<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use PHPUnit\Framework\TestCase;

final class HistoricalUpgradeMatrixContractTest extends TestCase
{
    public function test_v001_matrix_covers_every_processwire_module_and_current_version(): void
    {
        $matrix = $this->matrix();
        $root = dirname(__DIR__, 3);
        $discovered = array_merge(
            glob($root . '/*.module.php') ?: [],
            glob($root . '/packages/*/*.module.php') ?: [],
        );
        $expectedPaths = array_map(
            static fn (string $path): string => substr($path, strlen($root) + 1),
            $discovered,
        );
        $fixturePaths = array_column($matrix, 'modulePath');
        sort($expectedPaths);
        sort($fixturePaths);

        self::assertCount(36, $matrix);
        self::assertSame($expectedPaths, $fixturePaths);

        foreach ($matrix as $entry) {
            $source = file_get_contents($root . '/' . $entry['modulePath']);
            self::assertIsString($source);
            self::assertMatchesRegularExpression(
                "/'version'\s*=>\s*'" . preg_quote($entry['targetVersion'], '/') . "'/",
                $source,
                $entry['moduleName'] . ' fixture target must match getModuleInfo().',
            );
            self::assertLessThanOrEqual(
                (int) $entry['targetVersion'],
                (int) $entry['sourceVersion'],
                $entry['moduleName'] . ' source version cannot be newer than its target.',
            );
        }
    }

    public function test_every_historical_component_has_a_version_synchronizing_upgrade_hook(): void
    {
        $root = dirname(__DIR__, 3);

        foreach ($this->matrix() as $entry) {
            if (!$entry['upgradeHookRequired']) {
                continue;
            }

            $source = file_get_contents($root . '/' . $entry['modulePath']);
            self::assertIsString($source);
            $body = $this->methodBody($source, '___upgrade');
            self::assertNotNull($body, $entry['moduleName'] . ' must expose ___upgrade().');
            $effectiveBody = str_contains($body, '$this->___install();')
                ? $body . (string) $this->methodBody($source, '___install')
                : $body;
            self::assertStringContainsString(
                "markInstalled('{$entry['component']}', self::getModuleInfo()['version']",
                $effectiveBody,
                $entry['moduleName'] . ' must synchronize its registered version during upgrade.',
            );
            self::assertStringContainsString(
                "enable('{$entry['component']}')",
                $effectiveBody,
                $entry['moduleName'] . ' must leave its component enabled after upgrade.',
            );

            foreach ($entry['pendingMigrations'] as $migrationClass) {
                self::assertStringContainsString('MigrationRunner', $effectiveBody);
                self::assertStringContainsString(
                    (new \ReflectionClass($migrationClass))->getShortName(),
                    $effectiveBody,
                    $entry['moduleName'] . ' must run every migration introduced after v001.',
                );
            }
        }
    }

    /**
     * @return array<int, array{
     *   moduleName: string,
     *   modulePath: string,
     *   component: string|null,
     *   sourceVersion: string,
     *   targetVersion: string,
     *   upgradeHookRequired: bool,
     *   pendingMigrations: string[]
     * }>
     */
    private function matrix(): array
    {
        $json = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/historical-upgrade-v001.json');
        self::assertIsString($json);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private function methodBody(string $source, string $method): ?string
    {
        $tokens = token_get_all($source);
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!is_array($tokens[$index]) || $tokens[$index][0] !== T_FUNCTION) {
                continue;
            }
            $nameIndex = $index + 1;
            while ($nameIndex < $count
                && (!is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING)) {
                $nameIndex++;
            }
            if ($nameIndex >= $count || $tokens[$nameIndex][1] !== $method) {
                continue;
            }
            $bodyIndex = $nameIndex + 1;
            while ($bodyIndex < $count && $tokens[$bodyIndex] !== '{') {
                $bodyIndex++;
            }
            $depth = 0;
            $body = '';
            for (; $bodyIndex < $count; $bodyIndex++) {
                $token = $tokens[$bodyIndex];
                $body .= is_array($token) ? $token[1] : $token;
                if ($token === '{') {
                    $depth++;
                } elseif ($token === '}' && --$depth === 0) {
                    return $body;
                }
            }
        }

        return null;
    }
}
