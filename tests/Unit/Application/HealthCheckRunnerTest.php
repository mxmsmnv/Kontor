<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\HealthCheckRunner;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;
use PHPUnit\Framework\TestCase;

final class HealthCheckRunnerTest extends TestCase
{
    public function test_run_collects_results_and_contains_check_failures(): void
    {
        $healthy = new class implements HealthCheckInterface {
            public function key(): string
            {
                return 'healthy';
            }

            public function run(): HealthCheckResult
            {
                return new HealthCheckResult('ok', 'Ready.');
            }
        };
        $broken = new class implements HealthCheckInterface {
            public function key(): string
            {
                return 'broken';
            }

            public function run(): HealthCheckResult
            {
                throw new \RuntimeException('Probe exploded.');
            }
        };

        $results = (new HealthCheckRunner())->run([$healthy, $broken]);

        $this->assertCount(2, $results);
        $this->assertSame('ok', $results[0]['result']->status);
        $this->assertSame('critical', $results[1]['result']->status);
        $this->assertStringContainsString('Probe exploded.', $results[1]['result']->message);
    }
}
