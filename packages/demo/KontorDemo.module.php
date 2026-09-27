<?php

namespace ProcessWire;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Collaboration\Domain\Comment;
use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use Kontor\Demo\Application\DemoScenarioService;
use Kontor\Demo\Domain\DemoScenario;
use Kontor\Demo\Health\DemoHealthCheck;
use Kontor\Demo\Infrastructure\Mail\SimulatedMailSender;
use Kontor\Demo\Infrastructure\Persistence\DemoScenarioRepository;
use Kontor\Demo\Migrations\Migration0001CreateScenariosTable;
use Kontor\Payments\Domain\Payment;
use Kontor\Projects\Domain\BillableItem;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Tasks\Domain\Task;

/**
 * Executable reference integration for the whole Kontor stack.
 *
 * The module intentionally consumes the public repositories and services
 * of other components. It demonstrates how a real vertical coordinates
 * them without duplicating their business rules.
 */
class KontorDemo extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Demo',
            'summary' => 'Connected order-to-cash scenario across Kontor components.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/Kontor',
            'icon' => 'play-circle',
            'singular' => true,
            'autoload' => true,
            'requires' => [
                'Kontor',
                'KontorContacts',
                'KontorCRM',
                'KontorCatalog',
                'KontorSales',
                'KontorWorkflow',
                'KontorTasks',
                'KontorCollaboration',
                'KontorProjects',
                'KontorInvoices',
                'KontorPayments',
                'KontorFiles',
                'KontorMail',
                'KontorLedger',
                'KontorGermany',
            ],
            'permissions' => [
                'kontor-demo-view' => 'View Kontor demo scenarios',
                'kontor-demo-run' => 'Create and advance Kontor demo scenarios',
                'kontor-demo-approve' => 'Approve Kontor demo proposals',
            ],
        ];
    }

    private ?DemoScenarioRepository $scenarioRepository = null;
    private ?DemoScenarioService $scenarioService = null;

    public function scenarioRepository(): DemoScenarioRepository
    {
        return $this->scenarioRepository ??= new DemoScenarioRepository(
            $this->pdo(),
            $this->organizations(),
        );
    }

    public function scenarios(): DemoScenarioService
    {
        if ($this->scenarioService !== null) {
            return $this->scenarioService;
        }

        /** @var KontorWorkflow $workflow */
        $workflow = $this->wire()->modules->get('KontorWorkflow');

        return $this->scenarioService = new DemoScenarioService(
            $this->pdo(),
            $this->scenarioRepository(),
            $workflow->definitionRepository(),
            $workflow->transitionRepository(),
            $workflow->definitions(),
            $workflow->engine(),
        );
    }

    public function startScenario(string $organizationUid, int $actorUserId): DemoScenario
    {
        return $this->scenarios()->start(
            $organizationUid,
            'Connected order-to-cash demo',
            $actorUserId,
            fn (DemoScenario $scenario): array => $this->createIntake($scenario, $actorUserId),
        );
    }

    public function advanceScenario(
        string $scenarioUid,
        string $action,
        int $actorUserId,
    ): DemoScenario {
        $effect = match ($action) {
            'prepare_proposal' => fn (DemoScenario $scenario): array => $this->prepareProposal($scenario, $actorUserId),
            'request_approval' => static fn (): array => [],
            'start_delivery' => fn (DemoScenario $scenario): array => $this->startDelivery($scenario, $actorUserId),
            'issue_invoice' => fn (DemoScenario $scenario): array => $this->issueInvoice($scenario, $actorUserId),
            'settle' => fn (DemoScenario $scenario): array => $this->settle($scenario, $actorUserId),
            default => throw new WireException("Unknown demo action \"{$action}\"."),
        };

        return $this->scenarios()->advance(
            $scenarioUid,
            $action,
            $actorUserId,
            true,
            $effect,
        );
    }

    public function approveScenario(string $scenarioUid, int $actorUserId): DemoScenario
    {
        return $this->scenarios()->approve(
            $scenarioUid,
            $actorUserId,
            fn (DemoScenario $scenario): array => $this->approveProposal($scenario, $actorUserId),
        );
    }

    /**
     * @return array<int, array{module: string, title: string, status: string, message: string}>
     */
    public function componentChecks(): array
    {
        $checks = [];
        foreach ($this->componentModules() as $moduleName) {
            $info = $this->wire()->modules->getModuleInfo($moduleName);
            if (!$this->wire()->modules->isInstalled($moduleName)) {
                $checks[] = [
                    'module' => $moduleName,
                    'title' => (string) ($info['title'] ?? $moduleName),
                    'status' => 'critical',
                    'message' => 'Module is not installed.',
                ];
                continue;
            }

            try {
                $module = $this->wire()->modules->get($moduleName);
                if (is_object($module) && method_exists($module, 'healthCheck')) {
                    $result = $module->healthCheck()->run();
                    $status = $result->status;
                    $message = $result->message;
                } else {
                    $status = 'ok';
                    $message = 'Installed; no dedicated health check.';
                }
            } catch (\Throwable $exception) {
                $status = 'critical';
                $message = $exception->getMessage();
            }

            $checks[] = [
                'module' => $moduleName,
                'title' => (string) ($info['title'] ?? $moduleName),
                'status' => $status,
                'message' => $message,
            ];
        }

        return $checks;
    }

    public function healthCheck(): DemoHealthCheck
    {
        return new DemoHealthCheck($this->pdo());
    }

    /**
     * @return array<string, string>
     */
    private function createIntake(DemoScenario $scenario, int $actorUserId): array
    {
        /** @var KontorContacts $contacts */
        $contacts = $this->wire()->modules->get('KontorContacts');
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        /** @var KontorTasks $tasks */
        $tasks = $this->wire()->modules->get('KontorTasks');
        /** @var KontorCollaboration $collaboration */
        $collaboration = $this->wire()->modules->get('KontorCollaboration');

        $company = Company::create(
            $scenario->organizationId,
            'Northstar Workshop GmbH',
            tradingName: 'Northstar',
            email: 'office@northstar.demo',
            preferredCurrency: 'EUR',
            paymentTermsDays: 14,
            notes: 'Created by KontorDemo.',
            metadata: ['demoScenarioUid' => $scenario->uid->toString()],
        );
        $contacts->companyRepository()->save($company);

        $contact = Contact::create(
            $scenario->organizationId,
            'Mara',
            null,
            'Novak',
            email: 'mara@northstar.demo',
            jobTitle: 'Operations Director',
            preferredCurrency: 'EUR',
            source: 'kontor_demo',
            assignedUserId: $actorUserId,
            notes: 'Primary contact for the connected demo.',
            metadata: ['demoScenarioUid' => $scenario->uid->toString()],
        );
        $contacts->contactRepository()->save($contact);
        $contacts->membershipRepository()->save(new ContactCompanyMembership(
            $scenario->organizationId,
            $contact->uid->toString(),
            $company->uid->toString(),
            'decision_maker',
            'Operations',
            true,
            new \DateTimeImmutable('today'),
            null,
            ['demoScenarioUid' => $scenario->uid->toString()],
        ));

        $pipeline = $crm->pipelineRepository()->defaultForEntityType($scenario->organizationId, 'deal');
        if ($pipeline === null) {
            $pipeline = Pipeline::create(
                $scenario->organizationId,
                'Demo sales pipeline',
                isDefault: true,
                settings: ['demoScenarioUid' => $scenario->uid->toString()],
            );
            $crm->pipelineRepository()->save($pipeline);
            foreach ([
                ['qualified', ['en' => 'Qualified'], 30, 10, 'open'],
                ['won', ['en' => 'Won'], 100, 90, 'won'],
                ['lost', ['en' => 'Lost'], 0, 100, 'lost'],
            ] as [$key, $label, $probability, $order, $type]) {
                $crm->stageRepository()->save(Stage::create(
                    $pipeline->uid->toString(),
                    $key,
                    $label,
                    $probability,
                    $order,
                    $type,
                ));
            }
        }

        $lead = Lead::create(
            $scenario->organizationId,
            'Northstar process modernization',
            $contact->uid->toString(),
            $company->uid->toString(),
            'kontor_demo',
            'high',
            Money::ofMinor(148750, 'EUR'),
            $actorUserId,
            new \DateTimeImmutable('+2 days'),
            'Connected demo from intake through settlement.',
        );
        $crm->leadRepository()->save($lead);
        $dealUid = $crm->crmService()->convertLead($lead->uid->toString(), (string) $actorUserId);

        $task = Task::create(
            $scenario->organizationId,
            'Coordinate Northstar demo delivery',
            'Follow the connected workflow through approval, delivery and payment.',
            'high',
            $actorUserId,
            dueAt: new \DateTimeImmutable('+14 days'),
        );
        $tasks->taskRepository()->save($task);
        $tasks->relations()->linkToEntity(
            $scenario->organizationId,
            $task->uid->toString(),
            'deal',
            $dealUid,
        );
        $comment = $collaboration->commentService()->post(
            $scenario->organizationId,
            'deal',
            $dealUid,
            'KontorDemo created the qualified opportunity and assigned its delivery task.',
            $actorUserId,
        );

        return [
            'company' => $company->uid->toString(),
            'contact' => $contact->uid->toString(),
            'lead' => $lead->uid->toString(),
            'deal' => $dealUid,
            'task' => $task->uid->toString(),
            'comment' => $comment->uid->toString(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function prepareProposal(DemoScenario $scenario, int $actorUserId): array
    {
        /** @var KontorCRM $crm */
        $crm = $this->wire()->modules->get('KontorCRM');
        /** @var KontorCatalog $catalog */
        $catalog = $this->wire()->modules->get('KontorCatalog');
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        /** @var KontorFiles $files */
        $files = $this->wire()->modules->get('KontorFiles');

        $dealUid = $this->requiredEntity($scenario, 'deal');
        $crm->crmService()->closeDealWon($dealUid, (string) $actorUserId);

        $item = CatalogItem::create(
            $scenario->organizationId,
            ['en' => 'Process modernization workshop'],
            itemType: 'service',
            sku: 'DEMO-' . substr($scenario->uid->toString(), -8),
            description: ['en' => 'Discovery, workflow design and implementation workshop.'],
            unitCode: 'h',
            taxCode: 'standard',
            salesPrice: Money::ofMinor(12500, 'EUR'),
            metadata: ['demoScenarioUid' => $scenario->uid->toString()],
        );
        $catalog->itemRepository()->save($item);

        $quotation = Quotation::create(
            $scenario->organizationId,
            'company',
            $this->requiredEntity($scenario, 'company'),
            'EUR',
            $this->requiredEntity($scenario, 'contact'),
            $dealUid,
            new \DateTimeImmutable('+30 days'),
        );
        $sales->quotationRepository()->save($quotation);
        $line = DocumentLine::create(
            $scenario->organizationId,
            'quotation',
            $quotation->uid->toString(),
            'Process modernization workshop',
            10,
            Money::ofMinor(12500, 'EUR'),
            $item->uid->toString(),
            'service',
            $item->sku,
            'Connected discovery and implementation.',
            'h',
            taxCode: 'standard',
            taxRate: 19,
        );
        $sales->documentLineRepository()->save($line);
        $quotation->applyTotalsFromLines([$line]);
        $sales->quotationRepository()->save($quotation);

        $brief = $files->fileManager()->upload(
            $scenario->organizationId,
            'northstar-demo-brief.txt',
            "KontorDemo scenario {$scenario->uid}\nDeal {$dealUid}\nQuotation {$quotation->uid}\n",
            classification: 'internal',
            entityType: 'demo_scenario',
            entityUid: $scenario->uid->toString(),
            metadata: ['purpose' => 'demo-brief'],
            actorId: $actorUserId,
        );

        return [
            'catalog_item' => $item->uid->toString(),
            'quotation' => $quotation->uid->toString(),
            'quotation_line' => $line->uid->toString(),
            'file' => $brief['uid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function approveProposal(DemoScenario $scenario, int $actorUserId): array
    {
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        /** @var KontorMail $mail */
        $mail = $this->wire()->modules->get('KontorMail');

        $quotationUid = $this->requiredEntity($scenario, 'quotation');
        $sales->quotationWorkflow()->issue($quotationUid);
        $sales->quotationWorkflow()->send($quotationUid);
        $sales->quotationWorkflow()->accept($quotationUid);
        $order = $sales->conversionService()->convert($quotationUid);

        $message = $mail->outboundWithSender(new SimulatedMailSender())->send(
            $scenario->organizationId,
            null,
            'demo@kontor.local',
            ['mara@northstar.demo'],
            [],
            'Northstar proposal approved',
            'The connected KontorDemo proposal was approved and converted to a sales order.',
            $actorUserId,
        );
        $mail->entityLinking()->link(
            $scenario->organizationId,
            $message->uid->toString(),
            'quotation',
            $quotationUid,
            $actorUserId,
        );

        return [
            'order' => $order->uid->toString(),
            'mail_message' => $message->uid->toString(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function startDelivery(DemoScenario $scenario, int $actorUserId): array
    {
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        /** @var KontorProjects $projects */
        $projects = $this->wire()->modules->get('KontorProjects');
        /** @var KontorCollaboration $collaboration */
        $collaboration = $this->wire()->modules->get('KontorCollaboration');
        /** @var KontorTasks $tasks */
        $tasks = $this->wire()->modules->get('KontorTasks');

        $orderUid = $this->requiredEntity($scenario, 'order');
        $sales->orderWorkflow()->confirm($orderUid);

        $project = Project::create(
            $scenario->organizationId,
            'DEMO-' . substr($scenario->uid->toString(), -6),
            'Northstar process modernization',
            'company',
            $this->requiredEntity($scenario, 'company'),
            12500,
            'EUR',
            $actorUserId,
        );
        $projects->projectRepository()->save($project);
        $milestone = ProjectMilestone::create(
            $scenario->organizationId,
            $project->uid->toString(),
            'Connected workflow delivered',
            new \DateTimeImmutable('+14 days'),
        );
        $projects->milestoneRepository()->save($milestone);
        $time = $projects->timeTracking()->logManual(
            $scenario->organizationId,
            $project->uid->toString(),
            $actorUserId,
            new \DateTimeImmutable('-2 hours'),
            new \DateTimeImmutable(),
            'Demo workflow implementation',
            $milestone->uid->toString(),
            true,
            12500,
            'EUR',
        );
        $billable = BillableItem::create(
            $scenario->organizationId,
            $project->uid->toString(),
            'Connected demo handover',
            1,
            Money::ofMinor(23750, 'EUR'),
            $milestone->uid->toString(),
        );
        $projects->billableItemRepository()->save($billable);
        $comment = $collaboration->commentService()->post(
            $scenario->organizationId,
            'project',
            $project->uid->toString(),
            'Delivery started from approved quotation and confirmed sales order.',
            $actorUserId,
        );
        $tasks->workflow()->start($this->requiredEntity($scenario, 'task'));

        return [
            'project' => $project->uid->toString(),
            'milestone' => $milestone->uid->toString(),
            'time_entry' => $time->uid->toString(),
            'billable_item' => $billable->uid->toString(),
            'delivery_comment' => $comment->uid->toString(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function issueInvoice(DemoScenario $scenario, int $actorUserId): array
    {
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        /** @var KontorInvoices $invoices */
        $invoices = $this->wire()->modules->get('KontorInvoices');
        /** @var KontorGermany $germany */
        $germany = $this->wire()->modules->get('KontorGermany');
        /** @var KontorLedger $ledger */
        $ledger = $this->wire()->modules->get('KontorLedger');

        $orderUid = $this->requiredEntity($scenario, 'order');
        $sales->orderWorkflow()->complete($orderUid);
        $germany->chartOfAccountsSeeder()->seed($scenario->organizationId, $actorUserId);
        $invoice = $invoices->orderConversionService()->convert($orderUid);
        $invoices->workflow()->issue($invoice->uid->toString(), $actorUserId);
        $invoices->workflow()->send($invoice->uid->toString());
        $entry = $ledger->entryRepository()->findByReference(
            'invoice_issue',
            $invoice->uid->toString(),
        );

        return array_filter([
            'invoice' => $invoice->uid->toString(),
            'invoice_ledger_entry' => $entry?->uid->toString(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function settle(DemoScenario $scenario, int $actorUserId): array
    {
        /** @var KontorInvoices $invoices */
        $invoices = $this->wire()->modules->get('KontorInvoices');
        /** @var KontorPayments $payments */
        $payments = $this->wire()->modules->get('KontorPayments');
        /** @var KontorProjects $projects */
        $projects = $this->wire()->modules->get('KontorProjects');
        /** @var KontorTasks $tasks */
        $tasks = $this->wire()->modules->get('KontorTasks');

        $invoice = $invoices->invoiceRepository()->require($this->requiredEntity($scenario, 'invoice'));
        $payment = Payment::create(
            $scenario->organizationId,
            'company',
            $this->requiredEntity($scenario, 'company'),
            $invoice->due,
            'bank_transfer',
            new \DateTimeImmutable('today'),
            'DEMO-' . substr($scenario->uid->toString(), -8),
            metadata: ['demoScenarioUid' => $scenario->uid->toString()],
        );
        $payments->paymentRepository()->save($payment);
        $payments->workflow()->confirm($payment->uid->toString());
        $allocation = $payments->allocationService()->allocate(
            $payment->uid->toString(),
            'invoice',
            $invoice->uid->toString(),
            $invoice->due,
            $actorUserId,
        );
        $projects->milestones()->complete($this->requiredEntity($scenario, 'milestone'));
        $tasks->workflow()->complete($this->requiredEntity($scenario, 'task'));

        return [
            'payment' => $payment->uid->toString(),
            'payment_allocation' => $allocation->uid->toString(),
        ];
    }

    private function requiredEntity(DemoScenario $scenario, string $type): string
    {
        return $scenario->entity($type)
            ?? throw new \RuntimeException("Demo scenario is missing required \"{$type}\" entity.");
    }

    /**
     * @return string[]
     */
    private function componentModules(): array
    {
        return [
            'Kontor', 'KontorQueue', 'KontorFiles', 'KontorSearch', 'KontorAPI',
            'KontorContacts', 'KontorCatalog', 'KontorCRM', 'KontorSales',
            'KontorInvoices', 'KontorPayments', 'KontorTasks', 'KontorCollaboration',
            'KontorDashboard', 'KontorReports', 'KontorInventory', 'KontorPurchasing',
            'KontorExpenses', 'KontorProjects', 'KontorWorkflow', 'KontorAutomation',
            'KontorEntities', 'KontorGraphQL', 'KontorMarketplace', 'KontorMail',
            'KontorPortal', 'KontorCache', 'KontorDocuments', 'KontorAI',
            'KontorLedger', 'KontorGermany', 'KontorDemo',
        ];
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
        $runner = new MigrationRunner($this->pdo());
        $runner->ensureLedgerExists();
        $runner->run([new Migration0001CreateScenariosTable()]);

        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('demo', self::getModuleInfo()['version'], 'demo');
        $components->enable('demo');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('demo', self::getModuleInfo()['version'], 'demo');
        $components->enable('demo');
    }

    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Demo module removed. Demo scenarios and linked business data were kept intact.'));
    }
}
