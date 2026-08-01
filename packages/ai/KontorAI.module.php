<?php

namespace ProcessWire;

use Kontor\AI\Application\AIActionApprovalService;
use Kontor\AI\Application\AIGateway;
use Kontor\AI\Application\DraftingService;
use Kontor\AI\Application\ExtractionService;
use Kontor\AI\Application\ExternalApprovalService;
use Kontor\AI\Application\SummaryService;
use Kontor\AI\Health\AIHealthCheck;
use Kontor\AI\Infrastructure\Persistence\PendingAIActionRepository;
use Kontor\AI\Infrastructure\Providers\NullSquadClient;
use Kontor\AI\Infrastructure\Providers\PreviewAIProvider;
use Kontor\AI\Infrastructure\Providers\SquadAdapter;
use Kontor\AI\Infrastructure\Registry\AIProviderRegistry;
use Kontor\AI\Migrations\Migration0001CreatePendingActionsTable;
use Kontor\AI\Migrations\Migration0002CreateExternalApprovalsTable;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;

/**
 * KontorAI bootstrap module (kontor.md Substage 9.3, third component of
 * Stage 9). Depends only on kontor/core —
 * `KontorAIProviderInterface`/`AIRequest`/`AIResponse` already live in
 * kontor/sdk (kontor.md#9.12). "Kontor AI is optional" (kontor.md#32):
 * with no real Squad connection configured, `NullSquadClient` fails
 * cleanly rather than fabricating a response — a real deployment
 * supplies its own `SquadClientInterface`.
 */
class KontorAI extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor AI',
            'summary' => 'Provider contract, Squad adapter, summaries, drafting, extraction, approval workflow.',
            'version' => '003',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorAI',
            'icon' => 'magic',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-ai-action-approve' => 'Approve or reject pending AI actions',
            ],
        ];
    }

    private ?PendingAIActionRepository $pendingActionRepository = null;
    private ?AIProviderRegistry $providerRegistry = null;
    private ?AIGateway $gateway = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
        $this->providerRegistry()->register(new SquadAdapter(new NullSquadClient()));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorAI', $language, $strings);
        }
    }

    public function pendingActionRepository(): PendingAIActionRepository
    {
        return $this->pendingActionRepository ??= new PendingAIActionRepository($this->pdo(), $this->organizations());
    }

    public function providerRegistry(): AIProviderRegistry
    {
        return $this->providerRegistry ??= new AIProviderRegistry();
    }

    public function gateway(): AIGateway
    {
        return $this->gateway ??= new AIGateway($this->providerRegistry());
    }

    public function previewGateway(): AIGateway
    {
        $providers = new AIProviderRegistry();
        $providers->register(new PreviewAIProvider());

        return new AIGateway($providers);
    }

    public function previewApprovals(): AIActionApprovalService
    {
        return new AIActionApprovalService($this->previewGateway(), $this->pendingActionRepository());
    }

    public function summaries(): SummaryService
    {
        return new SummaryService($this->gateway());
    }

    public function drafting(): DraftingService
    {
        return new DraftingService($this->gateway());
    }

    public function extraction(): ExtractionService
    {
        return new ExtractionService($this->gateway());
    }

    public function approvals(): AIActionApprovalService
    {
        return new AIActionApprovalService($this->gateway(), $this->pendingActionRepository());
    }

    public function externalApprovals(): ExternalApprovalService
    {
        return new ExternalApprovalService($this->pdo(), $this->pendingActionRepository());
    }

    public function submitExternalApproval(string $provider, string $externalId, string $organizationUid, array $redactedMetadata, ?int $requestedBy = null): \Kontor\AI\Domain\PendingAIAction
    {
        return $this->externalApprovals()->submit($provider, $externalId, $organizationUid, $redactedMetadata, $requestedBy);
    }

    public function healthCheck(): AIHealthCheck
    {
        return new AIHealthCheck($this->pendingActionRepository());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreatePendingActionsTable(),
            new Migration0002CreateExternalApprovalsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('ai', self::getModuleInfo()['version'], 'ai');
        $components->enable('ai');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $runner = new MigrationRunner($this->pdo());
        $runner->ensureLedgerExists();
        $runner->run([new Migration0001CreatePendingActionsTable(), new Migration0002CreateExternalApprovalsTable()]);
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('ai', self::getModuleInfo()['version'], 'ai');
        $components->enable('ai');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor AI module removed. Pending AI actions were kept intact.'));
    }
}
