# Changelog

All notable changes to `kontor/files` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Fixed

- Entity file lookups can now be organization-scoped, preventing unrelated
  tenant rows from appearing in embedded business-document views.
- Scope version families, signed links, reads, archives, and restores to the
  owning organization; restoring an older version now archives the current
  version in that tenant only.
- Delete newly stored bytes if metadata persistence fails, avoiding orphaned
  private files.
- Use ProcessWire's general-purpose `tableSalt` (falling back to
  `userAuthSalt`) for signed URLs instead of the undefined `authSalt`
  configuration property.
- Keep the component registry version synchronized during module upgrades.

### Added

- Sales quotation issuance now stores confidential, quotation-bound PDFs.
- Documents is the first generated-output consumer: each PDF render creates
  an entity-bound private file version with its immutable document snapshot.
- First Files admin vertical: private uploads, metadata and checksum
  inspection, entity-bound version history, 15-minute signed downloads,
  and reversible archive/restore lifecycle.
- Initial alpha (Substage 2.2): `kontor_files` migration; `LocalPrivateStorage`
  (`StorageInterface`, path-traversal-safe); `SignedUrlSigner` (HMAC-SHA256,
  time-limited); `FileRepository` (versioning via
  `(entity_type, entity_uid, original_name)` + `version_number`,
  archive/restore); `FileManager` (upload with auto-versioning, signed
  URLs, `file.*` events); `FilesHealthCheck`; `KontorFiles.module.php`
  registering the `storage` capability into Kontor Core.
