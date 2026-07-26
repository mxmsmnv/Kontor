# Changelog

All notable changes to `kontor/workflow` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Expenses is the first business adopter: its safe default lifecycle is
  mirrored into a configurable `expenses.standard` instance with a real
  approval request and generic transition history.
- First ProcessKontor admin vertical: workflow definition and transition
  authoring, runtime instances, permission-aware actions, approval inbox,
  decisions, and immutable transition history.
- Initial alpha (Substage 7.1): `kontor_workflow_definitions`,
  `kontor_workflow_transitions`, `kontor_workflow_instances`,
  `kontor_workflow_approval_requests`, `kontor_workflow_history`
  migrations (kontor.md#18's workflow-definition shape, own schema
  design); `WorkflowDefinition`/`WorkflowTransition`/`WorkflowInstance`/
  `ApprovalRequest`/`HistoryEntry` domain objects;
  `DefinitionRepository`/`TransitionRepository` (implementing
  `RepositoryInterface` where applicable) and `InstanceRepository`/
  `ApprovalRequestRepository`/`HistoryRepository`;
  `WorkflowDefinitionService` (`defineWorkflow()`/`addTransition()` with
  real from/to state validation); `WorkflowEngine`
  (`start()`/`currentState()`/`availableActions()`/`transition()`
  (transition permissions + routes into an approval request when
  required)/`approve()`/`reject()`/`history()`); `WorkflowHealthCheck`;
  permissions; en/fr/de/es translations. First component of Stage 7
  (Extensibility) — a standalone, entity-agnostic engine, not retrofitted
  into any already-shipped component's own hardcoded workflow (see the
  README).
