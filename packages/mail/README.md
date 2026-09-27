# Kontor Mail

`kontor/mail` — outbound history, inbound adapters, entity linking,
shared mailboxes. First component of Stage 9 (Advanced capabilities).
kontor.md doesn't give a detailed mail specification — full gap-fill,
this substage's milestones are the four named in its own title. Depends
only on `kontor/core`.

## Entity linking reuses Core's own relations table

The "entity linking" milestone doesn't get a parallel linking table —
`EntityLinkingService` is a thin wrapper over
`Kontor\Core\Infrastructure\Persistence\RelationRepository`
(`kontor_relations`, kontor.md#11.7), the exact same reuse `kontor/tasks`
and `kontor/entities` already established for their own "relations"
milestones. A message links to any entity type (a contact, a deal, an
invoice, …) via `relationType = 'mail_link'`.

## Real events, not a closed system

`OutboundMailService`/`InboundMailService` publish `mail.sent`/
`mail.delivery_failed`/`mail.received` onto `kontor/core`'s real event
bus (kontor.md#21) — a genuine integration, not deferred, giving
`kontor/automation` real new triggers to react to (e.g. creating a
follow-up task when a send fails, the same value
`kontor/purchasing`/`kontor/projects` already got from their own
"integration" milestones).

## No third-party mail library

`RawEmailParser` is a small hand-rolled parser for a plain-text
RFC822-ish email (headers, a blank line, then body) — no MIME
library, the same "avoid a heavy dependency for a narrow need" call
`kontor/documents` made for its own template engine. It deliberately
doesn't handle MIME multipart, base64/quoted-printable encoding, or
attachments. `NativeMailSender` sends via PHP's own built-in `mail()`
rather than a third-party SMTP client — swappable later since callers
only ever depend on `MailSenderInterface`.

## Inbound adapters

`RawEmailForwardAdapter` (the one built-in adapter proving the
"inbound adapters" pipeline, the same "built once, adopted by whoever
wants it next" precedent every other registry in this monorepo follows)
simulates the common real-world shape of inbound mail: an external
provider's inbound-parse webhook (Postmark, SendGrid, or a plain
`.forward`/procmail pipe) hands Kontor a raw email via `pushRaw()`, and
`InboundMailService::poll()` drains it via `fetch()` — no live network
listener needed for this to be genuinely useful. A raw email that fails
to parse is silently dropped, not thrown, the same "one bad entry
doesn't stop the rest" behavior used throughout this monorepo.

## Contents

- `migrations/` — `kontor_mail_mailboxes` (the "shared mailboxes"
  milestone — standard columns in full, a real standalone entity),
  `kontor_mail_messages` (the "outbound history"/"inbound adapters"
  milestones' storage — `mailbox_uid` is nullable since an outbound
  message doesn't have to come from a shared mailbox).
- `src/Application/OutboundMailService.php` — persists the message as
  soon as it's built (`queued`), before the transport is even attempted,
  so history exists even if the process dies mid-send; the final
  `sent`/`failed` status is a second write to the same row.
- `src/Application/InboundMailService.php` — `poll()` drains a
  registered `InboundMailAdapterInterface` and persists every message it
  hands back.
- `src/Application/EntityLinkingService.php` — see above.
- `src/Application/MailboxService.php` — the "shared mailboxes"
  milestone's thin CRUD.
- `src/Health/MailHealthCheck.php` — flags failed outbound messages and
  unassigned inbound messages, not just a row count.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/` (`RawEmailParser`, `RawEmailForwardAdapter`,
`InboundMailAdapterRegistry`, `MailEventEmitter` against a fake
dispatcher, the `Mailbox`/`MailMessage` domain mutators) needs no
database and runs for real. `tests/Integration/` needs real MySQL (see
`../../docker-compose.test.yml`) and is skipped otherwise — the fourth
real consumer of `Kontor\Core\Testing\DatabaseTestCase` outside
`kontor/core`, after `kontor/api`, `kontor/graphql` and
`kontor/marketplace`.

## Not in scope for this substage

No real SMTP/IMAP client — `NativeMailSender` uses PHP's `mail()`;
a production SMTP adapter is a drop-in implementation of
`MailSenderInterface` for whoever needs one. No MIME
multipart/attachment parsing. No admin UI — same deferral every other
component in this monorepo has made, since none exists yet anywhere.
