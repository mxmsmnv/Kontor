# Changelog

All notable changes to `kontor/contacts` are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- Initial alpha (Substage 3.1) — the first business component:
  `kontor_contacts`, `kontor_companies`, `kontor_addresses`,
  `kontor_contact_company` migrations; `Contact`/`Company`/`Address`/
  `ContactCompanyMembership` domain objects; `ContactRepository`/
  `CompanyRepository` (implementing `RepositoryInterface`, registered into
  `RepositoryRegistry` for import rollback) plus `AddressRepository`/
  `MembershipRepository`; `TagService` (built on Core's new
  `ExtensionRepository`); `ContactDuplicateDetector`; real
  `ContactImportProvider`/`CompanyImportProvider`/`ContactExportProvider`/
  `CompanyExportProvider`; `ContactsHealthCheck`; search registration via
  `kontor/search`'s `SqlFullTextSearchProvider`; permissions
  (kontor.md#19.3); translations (en/fr/de/es) for validation messages and
  field labels.
- `kontor-contacts-merge` permission is declared but not yet implemented —
  duplicate *detection* is in this substage, merge is not.
