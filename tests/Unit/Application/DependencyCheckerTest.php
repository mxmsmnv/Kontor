<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\DependencyChecker;
use Kontor\Core\Domain\ComponentManifest;
use PHPUnit\Framework\TestCase;

final class DependencyCheckerTest extends TestCase
{
    private function manifest(array $overrides = []): ComponentManifest
    {
        return ComponentManifest::fromArray([
            'name' => 'KontorCRM',
            'version' => '1.0.0',
            'package' => 'kontor/crm',
            'namespace' => 'Kontor\\CRM',
            'requires' => ['php' => '>=8.2', 'processwire' => '>=3.0.240', 'kontor/core' => '^0.1'],
            ...$overrides,
        ]);
    }

    public function test_satisfied_when_everything_matches(): void
    {
        $core = ComponentManifest::fromArray([
            'name' => 'Kontor', 'version' => '0.1.0', 'package' => 'kontor/core',
            'namespace' => 'Kontor\\Core', 'requires' => [],
        ]);

        $result = (new DependencyChecker())->check(
            $this->manifest(),
            ['kontor/core' => $core],
            '8.3.0',
            '3.0.250',
        );

        $this->assertTrue($result->satisfied);
        $this->assertSame([], $result->problems());
    }

    public function test_missing_package_dependency_is_reported(): void
    {
        $result = (new DependencyChecker())->check($this->manifest(), [], '8.3.0', '3.0.250');

        $this->assertFalse($result->satisfied);
        $this->assertNotEmpty($result->missing);
    }

    public function test_unmet_php_version_is_reported(): void
    {
        $core = ComponentManifest::fromArray([
            'name' => 'Kontor', 'version' => '0.1.0', 'package' => 'kontor/core',
            'namespace' => 'Kontor\\Core', 'requires' => [],
        ]);

        $result = (new DependencyChecker())->check(
            $this->manifest(),
            ['kontor/core' => $core],
            '8.1.0',
            '3.0.250',
        );

        $this->assertFalse($result->satisfied);
    }

    public function test_conflicting_installed_package_is_reported(): void
    {
        $legacyCrm = ComponentManifest::fromArray([
            'name' => 'LegacyCRM', 'version' => '2.0.0', 'package' => 'acme/legacy-crm',
            'namespace' => 'Acme\\LegacyCRM', 'requires' => [],
        ]);
        $core = ComponentManifest::fromArray([
            'name' => 'Kontor', 'version' => '0.1.0', 'package' => 'kontor/core',
            'namespace' => 'Kontor\\Core', 'requires' => [],
        ]);

        $manifest = $this->manifest(['conflicts' => ['acme/legacy-crm' => '^2.0']]);

        $result = (new DependencyChecker())->check(
            $manifest,
            ['kontor/core' => $core, 'acme/legacy-crm' => $legacyCrm],
            '8.3.0',
            '3.0.250',
        );

        $this->assertFalse($result->satisfied);
        $this->assertNotEmpty($result->conflicts);
    }
}
