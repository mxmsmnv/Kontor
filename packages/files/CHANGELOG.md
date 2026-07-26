# Changelog

All notable changes to `kontor/files` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Fixed

- Use ProcessWire's general-purpose `tableSalt` (falling back to
  `userAuthSalt`) for signed URLs instead of the undefined `authSalt`
  configuration property.
- Keep the component registry version synchronized during module upgrades.

### Added

- Initial alpha (Substage 2.2): `kontor_files` migration; `LocalPrivateStorage`
  (`StorageInterface`, path-traversal-safe); `SignedUrlSigner` (HMAC-SHA256,
  time-limited); `FileRepository` (versioning via
  `(entity_type, entity_uid, original_name)` + `version_number`,
  archive/restore); `FileManager` (upload with auto-versioning, signed
  URLs, `file.*` events); `FilesHealthCheck`; `KontorFiles.module.php`
  registering the `storage` capability into Kontor Core.
