# Changelog

All notable changes to `kontor/collaboration` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 5.2): `kontor_notes`, `kontor_comments`,
  `kontor_mentions`, `kontor_followers`, `kontor_unread_states` migrations
  (schema gap-fill); `Note`/`Comment`/`Mention`/`Follower`/`UnreadState`
  domain objects; `NoteRepository`/`CommentRepository`
  (implementing `RepositoryInterface`, `CommentRepository::countSince()`
  for unread computation) and `MentionRepository`/`FollowerRepository`/
  `UnreadStateRepository`; `MentionParser` (`@123`-style extraction);
  `CommentService::post()` (mentions + auto-follow in one call);
  `UnreadStateService` (`markRead()`/`unreadCount()`);
  `CollaborationHealthCheck`; permissions; en/fr/de/es translations.
  Second component of Stage 5.
