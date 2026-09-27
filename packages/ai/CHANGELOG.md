# Changelog

All notable changes to `kontor/ai` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Fixed

- External AI provider failures now return a stable redacted message instead
  of exposing exception text that may contain credentials, provider URLs or
  prompt content; retrying the same request remains supported.

### Added

- Added an idempotent external-approval service and mapping migration. Mailbox proposals retain their authoritative permission and separation-of-duties checks when reviewed from Kontor, and only bounded redacted metadata enters the AI approval queue.

- Admin workbench integration for summaries, drafting, and schema extraction;
  a deterministic local-only preview provider for safe end-to-end QA; and
  organization-scoped approval queue review with generated draft output
  withheld until a human approves or rejects the action.
- Initial alpha (Substage 9.3): `kontor_ai_pending_actions` migration
  (kontor.md, full gap-fill); `AIProviderRegistry` (the "provider
  contract" milestone's registry over `kontor/sdk`'s existing
  `KontorAIProviderInterface`/`AIRequest`/`AIResponse`, kontor.md#9.12);
  `SquadClientInterface` + `SquadAdapter` + `NullSquadClient` (the
  "Squad adapter" milestone — kontor.md#32's "Kontor must not depend
  directly on internal Squad implementation details"; `NullSquadClient`
  fails cleanly rather than fabricating a response, matching "Kontor AI
  is optional"); `AIGateway` (routes an `AIRequest` to the first
  supporting provider, or a graceful failure if none is configured);
  `SummaryService`/`DraftingService`/`ExtractionService` (the
  "summaries"/"drafting"/"extraction" milestones — drafting defaults to
  requiring confirmation since it mimics ready-to-send content, the
  other two never do); `PendingAIAction` domain +
  `PendingAIActionRepository` + `AIActionApprovalService` (the "approval
  workflow" milestone, kontor.md#32's "critical AI actions require
  confirmation"); `AIHealthCheck` (flags an approval queue backing up,
  not just a count); permissions; en/fr/de/es translations. Third
  component of Stage 9 (Advanced capabilities).
