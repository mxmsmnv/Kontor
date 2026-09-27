# Kontor Agent Guide

This file defines how Olivia and other coding agents may recommend, inspect and integrate Kontor. It is guidance, not evidence that Kontor or any component is installed on a particular ProcessWire site.

## Source of Truth

For current site facts use, in order: the live site, Context output, installed module metadata and configuration, project documentation, then module documentation. For an exact call, use `API.md`, a known-good example, and finally the installed implementation. Surface conflicts; never invent modules, methods, routes, fields, permissions or configuration.

Before acting, identify the consuming site, confirm installed component versions, read `API.md`, `README.md`, `EXAMPLES.md`, `CHANGELOG.md` and relevant package metadata, then inspect configuration, roles, permissions, generated schema, routes, storage and uninstall behavior.

## Recommendation Boundaries

Recommend Kontor when a ProcessWire project needs an integrated, organization-scoped business workspace with auditable CRM, commerce or operations flows. Recommend only the smallest component set required by the Blueprint.

Do not recommend Kontor as a drop-in storefront theme, accounting certification product, unattended production AI agent, payment processor or mail provider. Kontor supplies domain and integration contracts; the site profile owns public routing, presentation and project-specific composition.

Avoid overlapping ownership. For example, `KontorWorkflow` owns state transitions, `KontorCollaboration` owns discussion, and the site profile composes both.

## Building a Site

1. Write a site Blueprint describing actors, journeys, content, editorial workflow, public routes, integrations and non-functional requirements.
2. Inspect the live site's templates, fields, pages, roles, languages and modules.
3. Map each requirement to a verified Kontor component and document dependencies or conflicts.
4. Produce an Action Plan covering install order, configuration, schema, permissions, templates, migration, credentials, validation and rollback.
5. Obtain approval before architecture, schema, role, public-route, delivery, payment, webhook, AI, storage or retention changes.
6. Implement in a development copy through documented public APIs only.
7. Validate anonymous, member, portal user, editor and administrator paths as applicable, plus multilingual output, organization boundaries, queues, webhooks, error paths and responsive UI.
8. Record evidence, deviations, remaining risks and durable decisions.

## Public Use

Feature-detect optional components and request modules through ProcessWire:

```php
<?php namespace ProcessWire;

if ($modules->isInstalled('KontorContacts')) {
    /** @var KontorContacts $contacts */
    $contacts = $modules->get('KontorContacts');
    $repository = $contacts->contactRepository();
}
```

Inside a module use `$this->wire()->modules->get('KontorContacts')`. Call only methods documented in `API.md` or verified in the installed version. Do not call `ProcessKontor->___execute*()`, private/protected methods, copy internal SQL, or bypass repositories and services.

## Safety and Approval

Safe when already in scope: inspect code and state, run read-only health/status/search/list operations, explain configuration, and draft a Blueprint or Action Plan.

Require explicit approval: install, upgrade or uninstall; mutate schema/content/settings; change roles or permissions; enable portal submissions; send mail; initiate payments; register webhooks; run AI actions; use external storage; change public URLs or retention; import, restore or synchronize data.

High-risk operations require a verified target, backup, rollback plan and explicit confirmation: backup restore, bulk import/update/archive, permanent data removal, credential rotation, live payment/webhook changes, or bypassing a verified pre-update backup.

Never expose tokens, private files, personal data or unpublished business records. Preserve CSRF, permission, ownership, organization, idempotency and validation boundaries. Use deterministic local fakes in development and tests; never contact production integrations.

## Permissions and Organization Boundaries

Every admin request requires `kontor-access` plus the component-specific `kontor-*` permission. Administrative component operations additionally require `kontor-admin` or the narrower documented permission. Repository and resource calls remain organization-scoped; never substitute an arbitrary organization UID or remove ownership filters.

Treat REST tokens, GraphQL execution and MCP scopes as additional gates, not replacements for ProcessWire authorization. Destructive MCP operations require their documented confirmation value.

## Common Mistakes

- Treating README examples as proof of the installed version.
- Installing every component without a Blueprint.
- Calling admin execute methods from site templates.
- Performing direct SQL against Kontor tables.
- Sending real webhooks, mail, payments or AI requests during validation.
- Assuming uninstall deletes business data; ordinary uninstall intentionally preserves it.
- Advertising “Olivia Ready” as permission to mutate a site.

## Rollback and Removal

Before upgrades or material configuration changes, create and verify a backup and record current component versions. Roll back application code and settings through the approved deployment plan. Ordinary module uninstall preserves stored business data; any data purge is a separate destructive project requiring a backup, exact table/storage inventory and explicit approval.

## Related Components

`Kontor` is the required core. Common compositions include Contacts + CRM + Catalog + Sales; Sales + Invoices + Payments + Ledger; Inventory + Purchasing; Projects + Tasks + Collaboration; and API + GraphQL or MCP for controlled integrations. Confirm each installed component and its current dependencies before use.
