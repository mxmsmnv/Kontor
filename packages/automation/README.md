# Kontor Automation

`kontor/automation` — triggers, conditions, actions, dry run, logs, and
recursion protection. Second component of Stage 7. kontor.md#30 gives the
pipeline shape — *"Trigger → Conditions → Actions"* — and a fuller feature
list (dry run, recursion protection, idempotency, retries, rate limits,
version history, approval gates, execution logs); this substage's actual
milestones are the narrower six in its own name — idempotency/retries/
rate limits/version history/approval gates are section 30's fuller list,
not this substage's, so they're deferred; see "Not in scope". Depends
only on `kontor/core`.

## Triggers are real Kontor events, not a second event system

A rule's `trigger_event` is a plain Kontor event name (kontor.md#21's
canonical envelope — `KontorEvent`), the exact same events
`kontor/inventory` (`inventory.movement.completed`),
`kontor/documents` (`document_template.published`) and every other
component already publish through `Kontor\Core\Infrastructure\Events\EventDispatcher`.
`KontorAutomation::init()` subscribes `AutomationEngine::handleEvent()` to
Core's real dispatcher for every distinct `trigger_event` across every
active rule — a genuine integration, not deferred, the same choice
`kontor/purchasing`/`kontor/projects` made for their own "integration"
milestones. New rules using an event name no rule currently uses take
effect from the next request (module `init()` runs once per request) —
acceptable for a synchronous, in-process bus.

## Contents

- `migrations/` — `kontor_automation_rules`, `kontor_automation_conditions`
  (AND semantics — every condition must pass), `kontor_automation_actions`,
  `kontor_automation_execution_logs` (append-only, one row per rule
  evaluation, matched or not).
- `src/Contracts/ActionHandlerInterface.php` /
  `src/Infrastructure/Registry/ActionHandlerRegistry.php` — the "actions"
  milestone's extension point. Not an SDK contract (kontor.md section 9
  has no automation-action entry) — same status as `kontor/dashboard`'s
  `WidgetProviderInterface`. `src/ActionHandlers/LogActionHandler.php` is
  a trivial built-in handler proving the pipeline end-to-end (and
  genuinely useful on its own as a debug/audit action) — real
  per-component handlers (send an email, create a task, …) are future work
  for those packages to build once this one exists to depend on.
- `src/Application/ConditionEvaluator.php` — the "conditions" milestone's
  actual evaluation: `equals`/`not_equals`/`greater_than`/`less_than`/
  `contains`, resolving a dot-path field against the triggering event's
  data the same way `kontor/documents`' `TemplateEngine` resolves
  placeholders.
- `src/Application/RuleDefinitionService.php` — `defineRule()`/
  `addCondition()`/`addAction()`, the last of which validates its
  `actionKey` is actually registered before it can be attached to a rule.
- `src/Application/AutomationEngine.php` — `handleEvent()`:
  - Evaluates every active rule matching the event's `trigger_event` (and
    organization) — the "triggers" milestone at runtime.
  - Runs `ConditionEvaluator` against the event's data — "conditions".
  - When matched and not a dry run, executes every action for the rule in
    order, logging each one's result; **one action failing doesn't stop
    the rest**, mirroring `EventDispatcher`'s own "a listener throwing
    does not stop the remaining listeners" behavior — "actions".
  - `$dryRun = true` reports what would match without invoking any
    handler — "dry run".
  - **Recursion protection**: a plain instance depth counter. Core's
    `EventDispatcher::dispatch()` is fully synchronous with no notion of
    call depth of its own — if an action handler causes a new `dispatch()`
    that re-enters this same engine instance's `handleEvent()` before the
    outer call returns, that's still the same PHP call stack, so the
    counter correctly catches it (capped at 5 levels) and logs a
    `recursion_blocked` entry instead of evaluating further.
  - Every evaluation — matched or not, dry run or real, blocked or not —
    writes a `kontor_automation_execution_logs` row — "logs".

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Application/ConditionEvaluatorTest.php` needs no database
(pure field-resolution/operator logic) and runs for real. Everything
under `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise, same
`KONTOR_TEST_DB_DSN` convention as the other packages — including a test
that deliberately drives the recursion guard to its limit through a
custom action handler that calls `handleEvent()` again.

## Not in scope for this substage

Idempotency, retries, rate limits, version history and approval gates
(kontor.md#30's fuller feature list) — none are this substage's own
milestones. No admin UI/API endpoints — rule authoring is
`RuleDefinitionService`'s method calls; a visual rule builder is future
work, same as `kontor/workflow`'s deferred visual editor. No built-in
action handlers beyond the one proving the pipeline.
