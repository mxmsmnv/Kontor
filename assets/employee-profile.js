(() => {
    const { profileUrl, displayName, avatar } = document.currentScript.dataset;
    const account = document.querySelector('.ws-header .ws-account');
    const panel = account?.querySelector('.ws-account-panel');
    if (!panel) return;

    const link = document.createElement('a');
    link.href = profileUrl;
    link.textContent = 'Employee profile';
    panel.querySelector('.ws-account-identity')?.after(link);

    account.querySelectorAll('.ws-account-name, .ws-account-identity strong').forEach((element) => {
        element.textContent = displayName;
    });
    if (!avatar) return;
    account.querySelectorAll('.ws-avatar').forEach((element) => {
        const image = document.createElement('img');
        image.src = avatar;
        image.alt = '';
        image.width = 32;
        image.height = 32;
        image.className = 'uk-border-circle';
        element.replaceChildren(image);
    });
})();
