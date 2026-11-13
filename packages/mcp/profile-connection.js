const mcpConnection = document.getElementById('kontor-mcp');

mcpConnection?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-mcp-copy]');
    if (!button) return;

    const field = document.getElementById(button.dataset.mcpCopy);
    const status = mcpConnection.querySelector('[data-mcp-copy-status]');
    try {
        await navigator.clipboard.writeText(field.value ?? field.textContent);
        status.textContent = 'Copied.';
    } catch {
        const selection = window.getSelection();
        if (field.select) {
            field.select();
        } else if (selection) {
            const range = document.createRange();
            range.selectNodeContents(field);
            selection.removeAllRanges();
            selection.addRange(range);
        }
        status.textContent = 'Selected. Press your copy shortcut.';
    }
});
