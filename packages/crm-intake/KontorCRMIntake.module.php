<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\CRMIntake\Application\CRMIntakeService;
use Kontor\CRMIntake\Health\CRMIntakeHealthCheck;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeProfileRepository;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeResponseRepository;
use Kontor\CRMIntake\Infrastructure\Settings\CRMIntakeSettingsProvider;
use Kontor\CRMIntake\Migrations\Migration0001CreateCRMIntakeTables;

class KontorCRMIntake extends WireData implements Module
{
    private ?IntakeProfileRepository $profiles = null;
    private ?IntakeResponseRepository $responses = null;
    private ?CRMIntakeService $service = null;

    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor CRM Intake',
            'summary' => 'Configurable qualification forms shared by contacts, leads and deals.',
            'version' => '100',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/Kontor',
            'icon' => 'list-alt',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorCRM'],
            'permissions' => [
                'kontor-crm-intake-admin' => 'Configure CRM intake profiles',
            ],
        ];
    }

    public function init(): void
    {
        if ($this->wire()->modules->isInstalled('KontorSettings')) {
            /** @var KontorSettings $settings */
            $settings = $this->wire()->modules->get('KontorSettings');
            if (!$settings->providerRegistry()->has('crm_intake')) {
                $settings->providerRegistry()->register(new CRMIntakeSettingsProvider(
                    $this->organizationUid(),
                    $this->profileRepository(),
                    $this->service(),
                ));
            }
        }
    }

    public function profileRepository(): IntakeProfileRepository
    {
        return $this->profiles ??= new IntakeProfileRepository($this->pdo(), $this->organizations());
    }

    public function responseRepository(): IntakeResponseRepository
    {
        return $this->responses ??= new IntakeResponseRepository($this->pdo(), $this->organizations());
    }

    public function service(): CRMIntakeService
    {
        return $this->service ??= new CRMIntakeService($this->profileRepository(), $this->responseRepository());
    }

    public function healthCheck(): CRMIntakeHealthCheck
    {
        return new CRMIntakeHealthCheck($this->organizationUid(), $this->profileRepository());
    }

    public function ___install(): void
    {
        $runner = new MigrationRunner($this->pdo());
        $runner->ensureLedgerExists();
        $runner->run([new Migration0001CreateCRMIntakeTables()]);
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('crm_intake', self::getModuleInfo()['version'], 'crm-intake');
        $components->enable('crm_intake');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $this->___install();
    }

    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor CRM Intake was removed. Profiles and captured answers were kept intact.'));
    }

    private function organizationUid(): string
    {
        return $this->organizations()->defaultOrganization('US', 'en', 'USD')->uid->toString();
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
    }
}
