(() => {
    const url = document.currentScript?.dataset.mcpAccountUrl;
    const panel = document.querySelector('.ws-account-panel');
    if (!url || !panel || panel.querySelector('[data-kontor-mcp-link]')) return;

    const link = document.createElement('a');
    link.href = url;
    link.dataset.kontorMcpLink = '';
    const label = document.createElement('span');
    label.textContent = 'MCP connections';
    link.append(label);
    const password = panel.querySelector('a[href$="/password/"]');
    if (password) {
        const icon = password.querySelector('svg, i');
        if (icon) link.prepend(icon.cloneNode(true));
        password.after(link);
    } else {
        panel.append(link);
    }
})();
