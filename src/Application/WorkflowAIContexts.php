<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

/** Static prompts for staff screens. Record data is supplied by the operator. */
final class WorkflowAIContexts
{
    public const PROMPTS = [
        'activity' => 'Summarize the activity and suggest what to review.',
        'api' => 'Explain the API response or example and suggest how to validate it.',
        'automation' => 'Draft a trigger, conditions and a dry-run checklist for this rule.',
        'backups' => 'Explain the backup status and suggest verification steps.',
        'cache' => 'Explain the cache metrics and suggest what to measure next.',
        'catalog' => 'Draft a catalog description and suggest a category.',
        'collaboration' => 'Summarize the discussion and identify open decisions.',
        'companies' => 'Draft a company handover brief.',
        'components' => 'Explain component dependencies and suggest checks.',
        'contacts' => 'Draft a contact handover brief from the supplied notes.',
        'crm' => 'Suggest CRM follow-up actions and highlight missing information.',
        'crm-intake' => 'Suggest qualification questions and explain why they matter.',
        'custom-entities' => 'Propose fields and validation rules for a custom entity.',
        'dashboard' => 'Explain the metrics, separate facts from interpretations, and suggest next steps.',
        'demo' => 'Explain the demo workflow and suggest what to test.',
        'documents' => 'Draft the requested document wording for review.',
        'expense' => 'Suggest an expense category and flag missing receipt details.',
        'files' => 'Suggest tags and a useful description for the supplied file notes.',
        'germany' => 'Draft German business wording using the supplied facts.',
        'graphql' => 'Explain this GraphQL schema or response.',
        'health' => 'Explain the diagnostic results and suggest checks.',
        'inventory' => 'Explain the inventory trends and highlight potential shortages.',
        'invoice' => 'Draft a payment reminder based on the supplied invoice facts.',
        'ledger' => 'Explain the accounting discrepancy and suggest checks to verify it.',
        'mail' => 'Draft a reply to the supplied email excerpt.',
        'marketplace' => 'Summarize the compatibility notes and flag uncertainties.',
        'organization' => 'Draft internal guidance based on the supplied organization policy.',
        'payment' => 'Suggest reconciliation checks for the supplied payment details.',
        'portal' => 'Draft customer help using the supplied public information.',
        'project' => 'Summarize progress and suggest next milestones.',
        'purchase-order' => 'Draft supplier notes and highlight missing order details.',
        'queue' => 'Summarize the job failures and suggest diagnostic checks.',
        'reports' => 'Explain the reported results and flag missing context.',
        'sales' => 'Draft a sales follow-up based on the supplied facts.',
        'search' => 'Suggest alternative terms for this search.',
        'settings-migration' => 'Explain the configuration differences and suggest verification steps.',
        'tasks' => 'Turn the supplied work notes into a short task checklist.',
        'workflow' => 'Propose states and transitions for this process.',
    ];

    public static function forTemplate(string $template): ?string
    {
        $aliases = [
            'automations' => 'automation', 'catalog-form' => 'catalog', 'catalog-categories' => 'catalog',
            'catalog-category-form' => 'catalog', 'catalog-price-entry-form' => 'catalog',
            'catalog-price-list-form' => 'catalog', 'catalog-price-lists' => 'catalog', 'catalog-references' => 'catalog',
            'contact-form' => 'contacts', 'entity-form' => 'contacts', 'crm-deals' => 'crm',
            'crm-lead' => 'crm', 'crm-pipeline' => 'crm', 'crm-intake-fields' => 'crm-intake',
            'custom-entity' => 'custom-entities', 'custom-entity-record' => 'custom-entities',
            'expense-category' => 'expense', 'expenses' => 'expense', 'inventory-movement' => 'inventory',
            'inventory-warehouse' => 'inventory', 'invoices' => 'invoice', 'payments' => 'payment',
            'projects' => 'project', 'purchasing' => 'purchase-order', 'purchasing-receipt' => 'purchase-order',
            'purchasing-supplier' => 'purchase-order', 'sales-order' => 'sales', 'sales-quotation' => 'sales',
            'workflow-instance' => 'workflow', 'workflows' => 'workflow',
        ];
        $context = $aliases[$template] ?? $template;

        return isset(self::PROMPTS[$context]) ? $context : null;
    }
}
