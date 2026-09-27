<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\BackupContext;
use Kontor\SDK\DTO\BackupEstimate;
use Kontor\SDK\DTO\BackupReader;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\BackupWriter;
use Kontor\SDK\DTO\RestoreContext;
use Kontor\SDK\DTO\RestoreResult;

interface BackupProviderInterface
{
    public function component(): string;

    public function estimate(BackupContext $context): BackupEstimate;

    public function export(BackupWriter $writer, BackupContext $context): void;

    public function verify(BackupReader $reader, BackupContext $context): BackupVerification;

    public function restore(BackupReader $reader, RestoreContext $context): RestoreResult;
}
