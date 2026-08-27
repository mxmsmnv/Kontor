# Kontor MCP API

`ProcessWire\\KontorMCP` declares `mcpProvider => true` and implements `mcpProviderInfo()` and `mcpTools()` for MCP Server 1.0.

The provider publishes 13 tools covering readiness, components, live resource schemas, bounded search, resource list/get/validate/create/update/archive, and settings export/preview/apply.

JSON record attributes, filters, sort instructions, sparse fields and settings profiles are passed as bounded JSON strings. The bridge decodes them only after MCP Server validates the closed outer schema, then validates resource names, capabilities, fields and value types against the live `ApiResourceSchema`.

No component-specific names are hardcoded. Install an optional component that registers an `ApiResourceInterface` with `KontorAPI::resourceRegistry()` and its resource immediately appears in `kontor_resources` and the generic record tools.
