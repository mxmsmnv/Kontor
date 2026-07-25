<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Tests\Unit\Application;

use Kontor\Marketplace\Application\AdvisoryService;
use Kontor\Marketplace\Domain\Advisory;
use PHPUnit\Framework\TestCase;

final class AdvisoryServiceTest extends TestCase
{
    public function test_filter_affecting_matches_the_version_constraint(): void
    {
        $advisories = [
            Advisory::create('kontor/widgets', '<0.1.1', 'high', 'XSS in renderer'),
            Advisory::create('kontor/widgets', '>=0.2.0', 'medium', 'Unrelated to 0.1.x'),
        ];

        $affecting010 = AdvisoryService::filterAffecting($advisories, '0.1.0');
        $affecting020 = AdvisoryService::filterAffecting($advisories, '0.2.0');

        $this->assertCount(1, $affecting010);
        $this->assertSame('XSS in renderer', $affecting010[0]->title);
        $this->assertCount(1, $affecting020);
        $this->assertSame('Unrelated to 0.1.x', $affecting020[0]->title);
    }

    public function test_filter_affecting_returns_empty_when_nothing_matches(): void
    {
        $advisories = [Advisory::create('kontor/widgets', '<0.1.0', 'low', 'Old bug')];

        $this->assertSame([], AdvisoryService::filterAffecting($advisories, '0.5.0'));
    }

    public function test_contains_critical(): void
    {
        $this->assertTrue(AdvisoryService::containsCritical([
            Advisory::create('kontor/widgets', '<1.0.0', 'critical', 'RCE'),
        ]));

        $this->assertFalse(AdvisoryService::containsCritical([
            Advisory::create('kontor/widgets', '<1.0.0', 'high', 'Not critical'),
        ]));

        $this->assertFalse(AdvisoryService::containsCritical([]));
    }
}
