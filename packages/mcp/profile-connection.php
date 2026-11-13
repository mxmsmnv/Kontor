<?php namespace ProcessWire;

$csrf = '<input type="hidden" name="' . $escape($token['name']) . '" value="' . $escape($token['value']) . '">';
$codex = 'codex mcp add kontor --url ' . escapeshellarg($url) . ' --bearer-token-env-var KONTOR_MCP_TOKEN';
$claude = json_encode(['mcpServers' => ['kontor' => [
    'type' => 'http', 'url' => $url, 'headers' => ['Authorization' => 'Bearer ${KONTOR_MCP_TOKEN}'],
]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<section id="kontor-mcp" class="uk-card uk-card-default uk-card-body uk-margin-top" aria-labelledby="kontor-mcp-title">
    <h2 id="kontor-mcp-title">MCP connections</h2>
    <h3>1. Create a personal token</h3>
    <p>Tokens expire after 30 days and use your current Kontor permissions.</p>
    <?php if ($issued !== null): ?>
        <div class="uk-alert-primary uk-padding-small">
            <label for="kontor-mcp-token">Copy this token now. It is shown once.</label>
            <input id="kontor-mcp-token" class="uk-input" readonly value="<?= $escape($issued['token']) ?>" autocomplete="off">
            <button class="uk-button uk-button-default" type="button" data-mcp-copy="kontor-mcp-token">Copy token</button>
        </div>
    <?php endif; ?>
    <form method="post" action="./#kontor-mcp" class="uk-form-stacked">
        <?= $csrf ?>
        <input type="hidden" name="kontor_mcp_action" value="create">
        <label class="uk-form-label" for="kontor-mcp-label">Device label</label>
        <input id="kontor-mcp-label" class="uk-input uk-form-width-medium" name="kontor_mcp_label" placeholder="Work laptop" maxlength="60" required>
        <label class="uk-form-label uk-margin-small-top" for="kontor-mcp-scope">Access</label>
        <select id="kontor-mcp-scope" class="uk-select uk-form-width-medium" name="kontor_mcp_scope">
            <option value="read">Read</option>
            <option value="draft">Read and edit</option>
        </select>
        <button class="uk-button uk-button-primary" type="submit">Create token</button>
    </form>
    <?php if ($clients): ?>
        <h4>Active tokens</h4>
        <?php foreach ($clients as $client): ?>
            <form method="post" action="./#kontor-mcp" class="uk-margin-small">
                <?= $csrf ?>
                <input type="hidden" name="kontor_mcp_action" value="revoke">
                <input type="hidden" name="kontor_mcp_client" value="<?= $escape($client['id']) ?>">
                <strong><?= $escape($client['label']) ?></strong>
                · <?= $escape(implode(', ', $client['scopes'])) ?>
                · expires <?= $escape(date('Y-m-d', (int) $client['expires_at'])) ?>
                <span class="uk-label<?= $client['verified_at'] ? ' uk-label-success' : '' ?>">
                    <?= $client['verified_at'] ? 'Connection verified' : ($client['last_used_at'] ? 'Token accepted · check pending' : 'Waiting for first connection') ?>
                </span>
                <?php if ($client['verified_at']): ?>
                    <time datetime="<?= $escape(gmdate(DATE_ATOM, $client['verified_at'])) ?>"><?= $escape(gmdate('Y-m-d H:i', $client['verified_at'])) ?> UTC</time>
                <?php endif; ?>
                <button class="uk-button uk-button-default uk-button-small" type="submit">Revoke</button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
    <h3>2. Configure your client</h3>
    <details open>
        <summary>Codex</summary>
        <pre id="kontor-mcp-codex"><?= $escape($codex) ?></pre>
        <button class="uk-button uk-button-default" type="button" data-mcp-copy="kontor-mcp-codex">Copy command</button>
    </details>
    <details class="uk-margin-small-top">
        <summary>Claude Code</summary>
        <p>Merge this entry into your project's <code>.mcp.json</code>.</p>
        <pre id="kontor-mcp-claude"><?= $escape($claude) ?></pre>
        <button class="uk-button uk-button-default" type="button" data-mcp-copy="kontor-mcp-claude">Copy configuration</button>
    </details>
    <h3>3. Start a session and verify</h3>
    <p>Run this in Bash. Paste your token at the hidden prompt. For Claude Code, replace <code>codex</code> with <code>claude</code>.</p>
    <pre id="kontor-mcp-start">(read -r -s -p 'Kontor token: ' KONTOR_MCP_TOKEN &amp;&amp; printf '\n' &amp;&amp; KONTOR_MCP_TOKEN="$KONTOR_MCP_TOKEN" codex)</pre>
    <button class="uk-button uk-button-default" type="button" data-mcp-copy="kontor-mcp-start">Copy startup command</button>
    <p>After the check, <a href="./?mcp_status=1#kontor-mcp">Refresh connection status</a>. Verification shows the last successful check.</p>
    <p>Open <code>/mcp</code> and ask your agent to call <code>kontor_status</code>. <a href="https://github.com/mxmsmnv/Kontor/blob/main/packages/mcp/AGENT-SETUP.md">Full setup guide</a></p>
    <p aria-live="polite" data-mcp-copy-status></p>
</section>
<script defer src="<?= $escape($asset) ?>"></script>
