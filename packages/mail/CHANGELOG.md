# Changelog

All notable changes to `kontor/mail` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Task reminder workers deliver assigned-user email through outbound history
  and link each message back to its task.
- Collaboration notification workers deliver mention and follower emails
  through the normal outbound service and history, linking each message back
  to its commented entity.
- First ProcessKontor admin vertical: shared-mailbox creation, safe outbound
  simulation and history, raw inbound ingestion, message detail, and entity
  linking.
- Initial alpha (Substage 9.1): `kontor_mail_mailboxes`,
  `kontor_mail_messages` migrations (kontor.md, full gap-fill — no
  dedicated schema section for this component); `Mailbox`/`MailMessage`
  domain objects; `MailboxRepository`/`MailMessageRepository`;
  `MailSenderInterface` + `NativeMailSender` (PHP's own `mail()`, no
  third-party SMTP library) and `InboundMailAdapterInterface` +
  `InboundMailAdapterRegistry` + `RawEmailForwardAdapter` (the one
  built-in adapter, wrapping a hand-rolled `RawEmailParser` — no MIME
  library); `OutboundMailService` (the "outbound history" milestone —
  every send attempt is recorded, not just successful ones);
  `InboundMailService` (the "inbound adapters" milestone's consumer
  side); `EntityLinkingService` (the "entity linking" milestone, reusing
  `kontor/core`'s own `kontor_relations` table directly rather than a
  parallel table — the same choice `kontor/tasks`/`kontor/entities`
  already made); `MailboxService` (the "shared mailboxes" milestone);
  `MailEventEmitter` publishing real `mail.sent`/`mail.delivery_failed`/
  `mail.received` events onto `kontor/core`'s event bus; `MailHealthCheck`
  (flags failed outbound messages and unassigned inbound messages);
  permissions; en/fr/de/es translations. First component of Stage 9
  (Advanced capabilities).
