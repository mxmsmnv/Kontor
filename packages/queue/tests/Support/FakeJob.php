<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Support;

use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;

final class FakeJob implements JobInterface
{
    public static int $handleCalls = 0;

    /** @var list<int> */
    public static array $reportedProgress = [];

    public function __construct(
        private readonly array $data = ['n' => 1],
        private readonly bool $throws = false,
    ) {
    }

    public static function reset(): void
    {
        self::$handleCalls = 0;
        self::$reportedProgress = [];
    }

    public function jobType(): string
    {
        return 'fake.job';
    }

    public function payload(): array
    {
        return $this->data;
    }

    public function handle(array $payload, JobProgressReporterInterface $progress): void
    {
        self::$handleCalls++;
        $progress->report(50);
        self::$reportedProgress[] = 50;

        if ($this->throws || ($payload['throws'] ?? false)) {
            throw new \RuntimeException('simulated job failure');
        }

        $progress->report(100);
        self::$reportedProgress[] = 100;
    }
}
