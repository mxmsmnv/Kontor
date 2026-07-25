<?php

declare(strict_types=1);

namespace Kontor\Files\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\Contracts\StorageInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Proves the configured storage adapter is actually writable/readable by
 * doing a real round-trip, rather than just checking configuration exists.
 */
final class FilesHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly StorageInterface $storage)
    {
    }

    public function key(): string
    {
        return 'files';
    }

    public function run(): HealthCheckResult
    {
        $probePath = '.health-check/' . bin2hex(random_bytes(8)) . '.txt';

        try {
            $this->storage->put($probePath, 'health-check');
            $found = $this->storage->exists($probePath);
            $this->storage->delete($probePath);

            if (!$found) {
                return new HealthCheckResult('critical', 'A file was written to storage but could not be found afterward.');
            }

            if ($this->storage->exists($probePath)) {
                return new HealthCheckResult('warning', 'Storage delete did not remove the health-check probe file.');
            }

            return new HealthCheckResult('ok', 'File storage is writable and readable.');
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "File storage is not writable: {$e->getMessage()}");
        }
    }
}
