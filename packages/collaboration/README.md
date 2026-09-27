# Kontor Collaboration

`kontor/collaboration` — notes, comments, mentions, followers, and unread
states. Second component of Stage 5. Depends on `kontor/core`, plus the
shared Queue and Mail components for asynchronous follower and mention
notifications. Every entity here is polymorphic (`entity_type`/`entity_uid`),
so it can attach to a contact, a deal, a task, anything, without a hard
dependency on that component.

## Its own schema gap

Sections 11–16 of kontor.md never gave Collaboration a schema section, so
all five tables in `migrations/` are this package's own gap-fill, same
situation Tasks was in.

## Contents

- `migrations/` — `kontor_notes`, `kontor_comments` (`parent_uid` threads
  replies under a root comment), `kontor_mentions` (lighter junction-style
  table, own `read_at`), `kontor_followers` (`UNIQUE(organization_id,
  entity_type, entity_uid, user_id)` makes following idempotent at the
  schema level, not just in code), `kontor_unread_states` (one row per
  user+entity, `last_read_at` — an *aggregate* per-thread unread state,
  not a per-comment read flag).
- `src/Application/MentionParser.php` — the "mentions" milestone's actual
  parsing: extracts `@123`-style ProcessWire user-id tokens from a comment
  body. Numeric ids, not `@username` — Kontor has no user-lookup service of
  its own to resolve a name against; an admin UI with autocomplete would
  insert the resolved `@<id>` token, the same way most editors do.
- `src/Application/CommentService.php` — ties "comments", "mentions" and
  "followers" together in one call: `post()` extracts mentions from the
  body (merged with any passed explicitly), skips a self-mention, records
  one `Mention` per unique other user, and auto-follows the entity for the
  comment's author — "you're now watching this thread because you replied"
  is common enough to be worth wiring rather than leaving to every caller.
- `src/Application/UnreadStateService.php` — the "unread states"
  milestone's per-thread flavor: `unreadCount()` is *computed*, not a
  denormalized counter — comments on the entity created after
  `last_read_at` (or the epoch, if the user has never read it at all),
  excluding the viewer's own comments.
- `kontor_mentions.read_at` is "unread states"' other flavor — per mention,
  independent of the thread-level state above.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/Unit/Application/MentionParserTest.php` needs no database (pure
regex extraction) and runs for real. Everything under `tests/Integration/`
needs real MySQL (see `../../docker-compose.test.yml`) and is skipped
otherwise, same `KONTOR_TEST_DB_DSN` convention as the other packages.

## Admin vertical

The root `ProcessKontor` module now exposes recent collaboration activity and
task-attached notes/comments. Posting a comment exercises the existing mention
parser and auto-follow behavior; opening the task marks its thread read.
Mentioned users and existing followers with valid ProcessWire email addresses
receive one idempotent `collaboration.notify` Queue job per comment. The worker
rebuilds that job through `JobRegistry`, delivers it through Mail, and therefore
keeps the normal outbound history, mail events, and a `mail_link` back to the
commented entity. The author is excluded and a mention wins over a duplicate
follower notification. A failed Mail transport fails the job so Queue's normal
retry and dead-letter policy remains authoritative.

## Not in scope for this substage

No notification preferences, digests, or rich HTML/MIME messages; notification
delivery is one plain-text email per recipient and comment. No API endpoints or
rich text/markdown rendering (`body` is stored and returned as plain text).
