# Kontor Workflow

`kontor/workflow` — state machine, transition permissions, approvals, and
history. First component of Stage 7 (Extensibility, kontor.md#36). No
dedicated schema section, permission list, or workflow diagram — sections
11–16 stop at Inventory — but kontor.md#18 does give a precise shape for
what a workflow definition must contain (workflow key, entity type,
states, transitions, transition permissions, validators, required fields,
approval requirements, hooks, events, immutable states, automatic
actions); this package's five tables are this package's own design of
that list. Depends only on `kontor/core`.

## A generic engine, not a retrofit

kontor.md#18 is explicit: *"A component must provide safe default
workflows even if `KontorWorkflow` is not installed."* Every business
component built so far already has one — `QuotationWorkflowService`,
`InvoiceWorkflowService`, `ExpenseWorkflowService`, and so on, each with
its own hardcoded states and transitions. This package does **not**
retrofit any of them to be driven by a configured `WorkflowDefinition`
instead — that would mean re-touching six already-released packages for a
single substage, and it would contradict the "safe default even if not
installed" requirement (those services must keep working with zero
knowledge of this package). `kontor/workflow` is a standalone,
entity-agnostic engine — like `kontor/dashboard`'s `WidgetRegistry`, other
components could opt an entity into a configured workflow by depending on
this package and calling into it; none do yet. See "Not in scope".

## Contents

- `migrations/` — `kontor_workflow_definitions` (`states_json` holds the
  full valid state list — a state with no rows in
  `kontor_workflow_transitions` is "immutable" by construction, no
  separate flag needed), `kontor_workflow_transitions` (one row per
  `(from_state, action_key)` — at most one destination), `kontor_workflow_instances`
  (one row per entity, its current state — one active instance per
  entity), `kontor_workflow_approval_requests`, `kontor_workflow_history`
  (append-only, like `kontor/inventory`'s movement ledger).
- `src/Application/WorkflowDefinitionService.php` — the "state machine"
  milestone's authoring side: `defineWorkflow()` + `addTransition()`,
  which validates both the from- and to-state against the definition's
  own declared state list before storing anything.
- `src/Application/WorkflowEngine.php` — everything else:
  - `start()` / `currentState()` — the running state machine.
  - `availableActions()` — the "visual editor" milestone's backend: which
    actions a UI should offer from wherever an entity currently is. No UI
    is built (no admin UI exists anywhere in this monorepo yet).
  - `transition()` — the "transition permissions" milestone.
    kontor.md#19.8's own permission list puts permission checks in admin
    controllers/API endpoints, not this engine, so the caller passes
    `$actorHasPermission` after checking it themselves; the engine only
    refuses to proceed when told it wasn't granted. When the matched
    transition's `requires_approval` is set, this creates a pending
    `ApprovalRequest` instead of moving the state.
  - `approve()` / `reject()` — the "approvals" milestone. `approve()`
    guards that the entity hasn't moved since the request was made.
  - `history()` — the "history" milestone: every transition that actually
    completed, oldest first. A pending (not yet decided) approval request
    never appears here — only `applyTransition()` writes a history row.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Domain/WorkflowDefinitionTest.php` needs no database and runs
for real. Everything under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages.

## Not in scope for this substage

No visual editor UI — see above. No validators/required-fields/hooks/
events/automatic-actions from kontor.md#18's fuller definition list —
states, transitions, transition permissions, approvals and history are
this substage's five actual milestones; the rest is future work on top of
this same schema shape. No component has been wired to actually use a
configured workflow instead of its own hardcoded one yet.
