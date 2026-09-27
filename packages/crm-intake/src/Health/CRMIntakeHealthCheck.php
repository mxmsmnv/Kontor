<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Health;

use Kontor\CRMIntake\Infrastructure\Persistence\IntakeProfileRepository;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class CRMIntakeHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly string $organizationUid,
        private readonly IntakeProfileRepository $profiles,
    ) {
    }

    public function key(): string
    {
        return 'crm_intake';
    }

    public function run(): HealthCheckResult
    {
        $profile = $this->profiles->defaultForOrganization($this->organizationUid);

        return $profile === null
            ? new HealthCheckResult('warning', 'CRM intake is installed, but no default profile is configured.')
            : new HealthCheckResult('ok', 'CRM intake profile is ready with ' . count($profile->fields) . ' field(s).');
    }
}
