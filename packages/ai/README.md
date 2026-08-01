# Kontor AI

`kontor/ai` — provider contract, Squad adapter, summaries, drafting,
extraction, approval workflow. Third component of Stage 9 (one more,
Substage 9.4 — Ledger and localizations, remains). kontor.md#32 gives
this component's architecture
(`KontorAI → business AI contracts → Squad adapter → provider adapters`)
and one hard rule: **Kontor AI is optional**. Depends only on
`kontor/core` — `KontorAIProviderInterface`/`AIRequest`/`AIResponse`
already live in `kontor/sdk` (kontor.md#9.12), so the "provider contract"
milestone was mostly already there; this package is what's built on top
of it.

## Optional means graceful, not exceptional

`AIGateway::execute()` looks up a registered provider supporting the
requested capability. If none is configured, it returns
`AIResponse(success: false, ...)` — never an exception. Every capability
service (`SummaryService`/`DraftingService`/`ExtractionService`) and
every caller of them must already handle AI simply not being available,
the same way `kontor/cache`/`kontor/search` treat their own capabilities
as optional platform infrastructure.

## Squad adapter: a boundary, not an integration

kontor.md#32: *"Kontor must not depend directly on internal Squad
implementation details."* `SquadAdapter` implements
`KontorAIProviderInterface` but only ever talks to Squad through its own
`SquadClientInterface` — a small, generic `complete(capability, input)`
boundary this package has no further knowledge of. `NullSquadClient` is
the one trivial built-in implementation, proving the pipeline when no
real Squad connection is configured (it fails cleanly, matching "Kontor
AI is optional" rather than fabricating a response) — the same "built
once, adopted by whoever wants it next" precedent every other
registry/adapter extension point in this monorepo follows (e.g.
`RawEmailForwardAdapter`). A real deployment supplies its own
`SquadClientInterface` wired to Squad's actual API.

## Three business capabilities, one shared gateway

- `SummaryService::summarize()` — read-only, never requires confirmation.
- `DraftingService::draft()` — always requires confirmation by default:
  a draft mimics ready-to-send content (an email reply, a document
  paragraph), so even though drafting itself changes nothing, the risk
  is a caller surfacing it as already-reviewed without a human actually
  looking at it.
- `ExtractionService::extract()` — proposes structured fields out of
  unstructured text; never requires confirmation on its own, since
  nothing is created or changed until a caller explicitly acts on the
  result.

All three build an `AIRequest` and go through `AIGateway`, never a
provider directly.

## Approval workflow

kontor.md#32: *"Critical AI actions require confirmation unless an
explicit approved automation policy allows them."*
`AIActionApprovalService::requestAndMaybeApprove()` runs a request
through `AIGateway` as usual, but when the response's
`requiresConfirmation` is true, the output is withheld from the caller
and stashed as a `PendingAIAction` instead — the caller gets the pending
record back, not the raw output, until a human calls `approve()`/
`reject()`.

External modules can submit an already-redacted approval through `KontorAI::submitExternalApproval()`. The provider/reference mapping is unique and idempotent. Mailbox decisions additionally require `mailbox-confirm-links` and are forwarded to Mailbox before Kontor changes local state; no message body, full URL, query token, or credential is copied into Kontor.

## Contents

- `migrations/` — `kontor_ai_pending_actions` (kontor.md, full gap-fill;
  mutated once, not append-only, so no `version`/`archived_at`, the same
  reasoning `kontor/api`'s webhook deliveries and `kontor/marketplace`'s
  advisories already used).
- `src/Infrastructure/Registry/AIProviderRegistry.php` — the "provider
  contract" milestone's registry.
- `src/Contracts/SquadClientInterface.php` /
  `src/Infrastructure/Providers/SquadAdapter.php` /
  `NullSquadClient.php` — the "Squad adapter" milestone.
- `src/Application/SummaryService.php` / `DraftingService.php` /
  `ExtractionService.php` — the "summaries"/"drafting"/"extraction"
  milestones.
- `src/Application/AIActionApprovalService.php` — the "approval
  workflow" milestone.
- `src/Health/AIHealthCheck.php` — flags an approval queue that's been
  silently backing up (pending actions older than 7 days), not just a
  count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Everything under `tests/Unit/` needs no database — `AIGateway`,
`AIProviderRegistry`, `SquadAdapter`/`NullSquadClient`, all three
capability services (against an echoing fake provider), and
`PendingAIAction`'s domain mutators all run for real.
`tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise — the sixth
real consumer of `Kontor\Core\Testing\DatabaseTestCase` outside
`kontor/core`.

## Not in scope for this substage

No real Squad wiring — `NullSquadClient` is the only shipped
`SquadClientInterface` implementation; a real one is a deployment
concern. No automation-policy exemptions from the approval gate
(kontor.md#32's "unless an explicit approved automation policy allows
them") — every action that requires confirmation always goes through
`AIActionApprovalService`'s pending queue in this substage; a policy
engine to bypass it for specific, pre-approved cases is future work. No
admin UI for reviewing/approving pending actions — same deferral every
other component's real UI has made, since none exists anywhere in this
monorepo yet.
