<?php

declare(strict_types=1);

namespace Kontor\Reports\Infrastructure\Queue;

use Kontor\Files\Application\FileManager;
use Kontor\Reports\Application\ScheduledReportService;
use Kontor\Reports\Domain\ScheduledReport;
use Kontor\SDK\Contracts\JobInterface;
use Kontor\SDK\Contracts\JobProgressReporterInterface;
use RuntimeException;

final class ScheduledReportJob implements JobInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly ?ScheduledReportService $reports = null,
        private readonly ?FileManager $files = null,
    ) {
    }

    public static function forSchedule(ScheduledReport $schedule): self
    {
        return new self([
            'scheduleUid' => $schedule->uid->toString(),
            'organizationUid' => $schedule->organizationId,
            'providerKey' => $schedule->providerKey,
            'name' => $schedule->name,
            'format' => strtolower($schedule->format),
            'createdBy' => $schedule->createdBy,
        ]);
    }

    public function jobType(): string
    {
        return 'reports.scheduled';
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function handle(array $payload, JobProgressReporterInterface $progress): void
    {
        if ($this->reports === null || $this->files === null) {
            throw new RuntimeException(
                'ScheduledReportJob requires ScheduledReportService and FileManager; register its queue factory.'
            );
        }

        $directory = sys_get_temp_dir().'/kontor-scheduled-report-'.bin2hex(random_bytes(8));
        if (!mkdir($directory, 0700) && !is_dir($directory)) {
            throw new RuntimeException('Could not create the scheduled report export directory.');
        }

        $path = null;
        try {
            $progress->report(20);
            $path = $this->reports->export((string) $payload['scheduleUid'], $directory);
            $progress->report(70);

            $name = trim((string) $payload['name']);
            $safeName = trim((string) preg_replace('/[^a-z0-9_-]+/i', '-', $name), '-');
            $originalName = ($safeName !== '' ? $safeName : (string) $payload['scheduleUid'])
                .'.'.strtolower((string) $payload['format']);
            $contents = fopen($path, 'rb');
            if ($contents === false) {
                throw new RuntimeException('Could not read the scheduled report export.');
            }

            try {
                $this->files->upload(
                    organizationUid: (string) $payload['organizationUid'],
                    originalName: $originalName,
                    contents: $contents,
                    visibility: 'private',
                    classification: 'confidential',
                    entityType: 'scheduled_report',
                    entityUid: (string) $payload['scheduleUid'],
                    metadata: [
                        'providerKey' => (string) $payload['providerKey'],
                        'generatedBy' => 'reports.scheduled',
                    ],
                    actorId: isset($payload['createdBy']) ? (int) $payload['createdBy'] : null,
                );
            } finally {
                fclose($contents);
            }

            $this->reports->completeRun((string) $payload['scheduleUid']);
            $progress->report(100);
        } finally {
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
            @rmdir($directory);
        }
    }
}
