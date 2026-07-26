<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\ComponentOverviewBuilder;
use PHPUnit\Framework\TestCase;

final class ComponentOverviewBuilderTest extends TestCase
{
    public function test_builds_runtime_overview_and_detects_version_drift(): void
    {
        $overview = (new ComponentOverviewBuilder())->build(
            [
                ['name' => 'core', 'version' => '020', 'status' => 'enabled'],
                ['name' => 'queue', 'version' => '001', 'status' => 'enabled'],
            ],
            [
                'Kontor' => [
                    'installed' => true,
                    'title' => 'Kontor',
                    'version' => '020',
                    'versionStr' => '0.2.0',
                    'requires' => ['ProcessWire'],
                ],
                'KontorQueue' => [
                    'installed' => true,
                    'title' => 'Kontor Queue',
                    'version' => '003',
                    'versionStr' => '0.0.3',
                    'requires' => ['Kontor'],
                ],
            ],
        );

        $this->assertSame(['total' => 2, 'enabled' => 2, 'attention' => 1], $overview['counts']);
        $this->assertTrue($overview['components'][0]['versionInSync']);
        $this->assertFalse($overview['components'][1]['versionInSync']);
        $this->assertSame('0.0.1', $overview['components'][1]['registryVersion']);
        $this->assertSame(['Kontor'], $overview['components'][1]['requires']);
    }

    public function test_filters_by_search_and_attention_status(): void
    {
        $overview = (new ComponentOverviewBuilder())->build(
            [
                ['name' => 'core', 'version' => '020', 'status' => 'enabled'],
                ['name' => 'queue', 'version' => '001', 'status' => 'enabled'],
            ],
            [
                'Kontor' => ['installed' => true, 'title' => 'Kontor', 'version' => '020'],
                'KontorQueue' => [
                    'installed' => true,
                    'title' => 'Kontor Queue',
                    'summary' => 'Background jobs',
                    'version' => '003',
                ],
            ],
            query: 'background',
            status: 'attention',
        );

        $this->assertCount(1, $overview['components']);
        $this->assertSame('queue', $overview['components'][0]['name']);
        $this->assertSame('KontorQueue', (new ComponentOverviewBuilder())->moduleName('queue'));
    }
}
