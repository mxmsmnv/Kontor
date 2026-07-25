# Kontor Files

`kontor/files` — local private storage, file metadata, signed download
URLs, and versions. Implements `Kontor\SDK\Contracts\StorageInterface`
(spec section 9.11) and registers itself as the `storage` capability in
Kontor Core's `CapabilityRegistry`.

Separate component from Kontor Core (spec section 5.3, platform
repositories), same pattern as `kontor/queue`: depends on `kontor/core`,
not the other way around.

## Contents

- `KontorFiles.module.php` — bootstrap module; installs `kontor_files`
  (kontor.md#11.6) and registers the `storage` capability.
- `src/Infrastructure/Storage/LocalPrivateStorage.php` — the
  `StorageInterface` implementation. `FileManager` and every consumer
  depend only on the interface, never on this class directly — that's what
  makes an S3/R2/B2/SFTP adapter (spec section 5.5) a drop-in replacement.
  Rejects path traversal (absolute paths, `..` segments).
- `src/Infrastructure/Storage/SignedUrlSigner.php` — HMAC-SHA256 signed,
  time-limited download links. Produces/verifies the signature only; an
  actual HTTP endpoint that calls `verify()` and streams the file is
  admin-route wiring for a later stage (`KontorFiles::storage()` currently
  points `temporaryUrl()` at a placeholder admin URL).
- `src/Infrastructure/Persistence/FileRepository.php` — versions of "the
  same file" are separate rows sharing
  `(entity_type, entity_uid, original_name)` with an incrementing
  `version_number` — the schema has no separate family/group column, so
  that triple is the version key. Superseding a version archives the old
  row rather than deleting it (codex rule #10); its bytes stay readable.
- `src/Application/FileManager.php` — upload (auto-versions when the same
  entity + filename already has a current version), signed URLs, archive/
  restore, version history. Resolves an organization uid to its internal id
  via Core's `OrganizationRepository`.
- `src/Health/FilesHealthCheck.php` — a real write/read/delete round-trip
  against the configured storage, not just a config check.

## Not in scope for this substage

Previews, a virus-scan adapter, retention policies, and duplicate detection
(spec section 27 lists them; Substage 2.2's milestones — local private
storage, file metadata, signed URLs, permissions, versions, external
adapter interface — don't) are later enhancements.

## Testing

```bash
composer install
vendor/bin/phpunit
```

Integration tests need real MySQL (see `../../docker-compose.test.yml`) and
are skipped otherwise — same `KONTOR_TEST_DB_DSN` convention as the other
packages.
