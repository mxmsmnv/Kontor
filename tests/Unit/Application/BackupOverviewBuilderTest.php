<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\BackupOverviewBuilder;
use PHPUnit\Framework\TestCase;

final class BackupOverviewBuilderTest extends TestCase
{
    public function test_filters_and_paginates_backup_summaries(): void
    {
        $backups = [];

        for ($i = 1; $i <= 22; $i++) {
            $backups[] = [
                'id' => sprintf('contacts-snapshot-%02d', $i),
                'component' => 'contacts',
                'kind' => 'snapshot',
                'verified' => $i !== 22,
            ];
        }

        $backups[] = [
            'id' => 'core-full-01',
            'component' => 'core',
            'kind' => 'full',
            'verified' => true,
        ];

        $overview = (new BackupOverviewBuilder())->build(
            $backups,
            query: 'snapshot',
            component: 'contacts',
            status: 'verified',
            page: 2,
            pageSize: 20,
        );

        $this->assertSame(21, $overview['total']);
        $this->assertSame(2, $overview['page']);
        $this->assertSame(2, $overview['totalPages']);
        $this->assertCount(1, $overview['backups']);
        $this->assertSame('contacts-snapshot-21', $overview['backups'][0]['id']);
    }

    public function test_clamps_an_out_of_range_page_after_filtering(): void
    {
        $overview = (new BackupOverviewBuilder())->build(
            [[
                'id' => 'broken-backup',
                'component' => 'unknown',
                'kind' => 'unknown',
                'verified' => false,
            ]],
            status: 'failed',
            page: 99,
        );

        $this->assertSame(1, $overview['page']);
        $this->assertSame(1, $overview['total']);
    }
}
