# Kontor MCP

[Agent setup for Codex and Claude Code](AGENT-SETUP.md).

Kontor MCP is the optional provider component connecting Kontor to [MCP Server](https://github.com/mxmsmnv/McpServer). MCP Server owns authenticated Streamable HTTP transport, client identities, scopes, rate limits and audit evidence; this component owns the Kontor operations exposed through that boundary.

Version 1.0.1 additionally provides an opt-in, read-only PocketWire SDK surface. A matching ProcessWire actor with `kontor-access` can inspect bounded component/resource metadata, search, and read organization-scoped API resources only when the relevant component view permission is explicitly mapped. Unknown or dynamic resources fail closed for non-superusers. PocketWire receives no write actions, settings profiles, secrets, raw files, or external integrations.

## Access model

Each MCP client must be linked to one active ProcessWire user. Issue one client per user and device in **Setup → MCP Server → Clients**, then add `client-id:user-id` to **Modules → Kontor MCP → MCP client accounts**. The client ID is public metadata, not the bearer token. Existing clients must also be linked before using Kontor tools.

The gateway scopes and the linked user's current ProcessWire permissions both apply:

- `read` permits discovery and record reads. Contact validation also requires the corresponding create or edit permission.
- `draft` adds contact creation and updates when the linked user holds the corresponding permission.
- `admin` adds reviewed archival where the linked user has the archive permission. Settings tools require a linked superuser.

Employee discovery and search are limited to explicitly mapped resources and permissions. Contact search requires the explicit `contact` entity type. Revoke the client in MCP Server before removing its binding. A revoked or unlinked client loses access to Kontor tools.

“Full access” remains bounded: there is no PHP, shell, SQL, filesystem, module installation, permission management, secret retrieval or raw-file tool. Every input schema is closed and bounded, writes use stable identifiers and idempotency keys, and archive/settings apply require explicit confirmation phrases.

## Optional components

The provider reads `KontorAPI::resourceRegistry()`. A component contributes CRUD only when it is installed and has registered its own `ApiResourceInterface`; uninstalling an optional component automatically removes its resources from MCP discovery. `KontorSearch` and `KontorSettings` add search and settings migration when installed.

## Installation

1. Install `McpServer`, `Kontor`, `KontorAPI` and this `KontorMCP` module.
2. Open **Setup → MCP Server → Providers** and review the Kontor tools.
3. Keep the endpoint disabled until host, Origin, environment and namespace settings are reviewed.
4. Create a separate client identity. Use `admin` only for a runtime that genuinely needs every published tool.
5. Copy the bearer token once to the client’s secret environment. Never store it in Git or Kontor settings.
