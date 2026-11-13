# Connect an agent to Kontor MCP

Use this guide to connect Codex or Claude Code to a Kontor workspace. Modenza uses Streamable HTTP at `https://office.modenza.org/mcp/`. For another installation, get the endpoint from its administrator.

## 1. Provision personal access

Sign in to your Kontor account and open **Profile → Manage MCP connections**. Create a personal token, choose your client, and copy the setup instructions. Tokens created there are automatically linked to your user and expire after 30 days. You can revoke them from the same page.

For an administrator-managed token:

1. An administrator issues a separate credential for the employee and device in [MCP Clients](https://office.modenza.org/roisin/setup/mcp-server/clients/). Select **Read** for lookup or **Draft** for supported record changes.
2. In **Modules → Kontor MCP → MCP client accounts**, add `CLIENT_ID:USER_ID` using the employee's ProcessWire user ID. The active client IDs appear next to the field. Both the token scope and the linked user's Workspace permissions apply.
3. Have the employee enter the token locally as `KONTOR_MCP_TOKEN`. Keep the token out of source control, chat, command history, logs, and reports.

## 2. Configure the client

### Codex

Check for an existing entry with `codex mcp get kontor`. If it exists, verify its URL and token variable. Otherwise run:

```bash
codex mcp add kontor --url https://office.modenza.org/mcp/ \
  --bearer-token-env-var KONTOR_MCP_TOKEN
```

For manual setup, add this block to the active Codex `config.toml`:

```toml
[mcp_servers.kontor]
url = "https://office.modenza.org/mcp/"
bearer_token_env_var = "KONTOR_MCP_TOKEN"
```

The desktop app needs the token variable in its process environment at startup. Start with the CLI to verify the connection. [OpenAI MCP documentation](https://developers.openai.com/codex/mcp)

### Claude Code

Merge the `kontor` entry into the project's `.mcp.json`. Preserve any existing MCP entries. If prompted, have the user approve the project server. [Claude Code MCP documentation](https://code.claude.com/docs/en/mcp)

```json
{
  "mcpServers": {
    "kontor": {
      "type": "http",
      "url": "https://office.modenza.org/mcp/",
      "headers": {
        "Authorization": "Bearer ${KONTOR_MCP_TOKEN}"
      }
    }
  }
}
```

### Start a session

Run this in Bash from the working directory. The employee enters the token at the hidden prompt. For Claude Code, replace the final `codex` with `claude`.

```bash
(
  read -r -s -p 'Kontor token: ' KONTOR_MCP_TOKEN &&
    printf '\n' &&
    KONTOR_MCP_TOKEN="$KONTOR_MCP_TOKEN" codex
)
```

## 3. Verify the connection

1. Open `/mcp` in a new session and check that `kontor` is connected.
2. Invoke the tool ending in `kontor_status`. Modenza exposes it as `modenza_kontor_status`. Then invoke the tool ending in `kontor_resources` and inspect the MCP tool list for additional operations.
3. Report the endpoint, access scope, and actual tool result. For an HTTP 401, check the endpoint, token expiry, and whether the client process received the variable. For a tool-level permission denial, check the client binding, scope, and Workspace role.

## 4. Run a first workflow

> Find the test record I specify in the working directory. Check the source fields and look for an existing match in Kontor. Show the proposed changes. Once I confirm, save the record, read it back through MCP, and report its ID and saved fields.

For Arsen OS, start in Arsen's working directory. Use the local index if available, verify the values against the source file, and send only the selected fields to Kontor. If a write is retried and the tool accepts an idempotency key, reuse the original key.
