# Changelog

All notable changes to `kontor/ai` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

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
