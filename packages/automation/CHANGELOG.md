# Changelog

All notable changes to `kontor/automation` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 7.2): `kontor_automation_rules`,
  `kontor_automation_conditions`, `kontor_automation_actions`,
  `kontor_automation_execution_logs` migrations (kontor.md#30's
  Trigger→Conditions→Actions pipeline, own schema design);
  `AutomationRule`/`RuleCondition`/`RuleAction`/`ExecutionLog` domain
  objects; `ActionHandlerInterface` (own extension point) +
  `ActionHandlerRegistry`; `LogActionHandler` (built-in, proves the
  pipeline); `RuleRepository`/`ConditionRepository`/`ActionRepository`/
  `ExecutionLogRepository`; `ConditionEvaluator` (dot-path field
  resolution, `equals`/`not_equals`/`greater_than`/`less_than`/`contains`);
  `RuleDefinitionService` (`defineRule()`/`addCondition()`/`addAction()`,
  the last validated against the action handler registry);
  `AutomationEngine::handleEvent()` — real trigger matching, condition
  evaluation, action execution (one failure doesn't stop the rest), dry
  run, and instance-level recursion protection (max depth 5); real
  integration with `kontor/core`'s `EventDispatcher` — `KontorAutomation::init()`
  subscribes the engine to every distinct active `trigger_event`;
  `AutomationHealthCheck`; permissions; en/fr/de/es translations. Second
  component of Stage 7.
