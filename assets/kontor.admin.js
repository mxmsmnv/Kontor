document.addEventListener('DOMContentLoaded', () => {
  if (document.body.classList.contains('ProcessKontor')) {
    const contentBody = document.getElementById('pw-content-body');
    contentBody?.replaceWith(...contentBody.childNodes);
  }

  document.querySelectorAll('.ProcessKontor').forEach((workspace) => {
    workspace.querySelectorAll('input').forEach((input) => {
      const type = (input.getAttribute('type') || 'text').toLowerCase();

      if (type === 'checkbox') {
        input.classList.add('uk-checkbox');
      } else if (type === 'radio') {
        input.classList.add('uk-radio');
      } else if (type === 'range') {
        input.classList.add('uk-range');
      } else if (!['hidden', 'submit', 'button', 'reset', 'file'].includes(type)) {
        input.classList.add('uk-input');
      }
    });
    workspace.querySelectorAll('select').forEach((select) => select.classList.add('uk-select'));
    workspace.querySelectorAll('textarea').forEach((textarea) => textarea.classList.add('uk-textarea'));
    workspace.querySelectorAll('button:not(.uk-button)').forEach((button) => {
      button.classList.add('uk-button', 'uk-button-default');
    });
  });

  document.querySelectorAll('[data-kontor-bulk-form]').forEach((form) => {
    const formId = form.getAttribute('id');
    const selectAll = document.querySelector(`[data-kontor-select-all="${formId}"]`);
    const checkboxes = Array.from(document.querySelectorAll(`[data-kontor-select-item="${formId}"]`));
    const count = form.querySelector('[data-kontor-selected-count]');
    const entityLabel = form.dataset.entityLabel || 'item';

    const update = () => {
      const selected = checkboxes.filter((checkbox) => checkbox.checked).length;

      if (count) {
        count.textContent = `${selected} selected`;
      }

      if (selectAll) {
        selectAll.checked = selected > 0 && selected === checkboxes.length;
        selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
      }
    };

    selectAll?.addEventListener('change', () => {
      checkboxes.forEach((checkbox) => {
        checkbox.checked = selectAll.checked;
      });
      update();
    });
    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', update));

    form.addEventListener('submit', (event) => {
      const selected = checkboxes.filter((checkbox) => checkbox.checked).length;

      if (selected === 0) {
        event.preventDefault();
        window.alert(`Select at least one ${entityLabel}.`);
        return;
      }

      const action = event.submitter?.dataset.actionLabel || form.dataset.actionLabel || 'Change';

      if (!window.confirm(`${action} ${selected} selected ${entityLabel}(s)?`)) {
        event.preventDefault();
      }
    });

    update();
  });

  document.querySelectorAll('[data-kontor-directory]').forEach((directory) => {
    const search = directory.querySelector('[data-kontor-directory-search]');
    const items = Array.from(directory.querySelectorAll('[data-kontor-directory-item]'));
    const toggles = Array.from(directory.querySelectorAll('[data-kontor-quick-toggle]'));
    const count = directory.querySelector('[data-kontor-quick-count]');
    const visibleCount = directory.querySelector('[data-kontor-directory-visible-count]');
    const empty = directory.querySelector('[data-kontor-directory-empty]');
    const limit = Number.parseInt(directory.dataset.limit || '8', 10);

    const updateQuickAccess = () => {
      const selected = toggles.filter((toggle) => toggle.checked).length;

      if (count) {
        count.textContent = String(selected);
      }
      toggles.forEach((toggle) => {
        toggle.disabled = !toggle.checked && selected >= limit;
      });
    };

    search?.addEventListener('input', () => {
      const query = search.value.trim().toLocaleLowerCase();
      let visible = 0;

      items.forEach((item) => {
        item.hidden = query !== '' && !item.dataset.search.includes(query);
        if (!item.hidden) {
          visible += 1;
        }
      });
      directory.querySelectorAll('.kontor-directory__group').forEach((group) => {
        group.hidden = !group.querySelector('[data-kontor-directory-item]:not([hidden])');
      });
      if (visibleCount) {
        visibleCount.textContent = String(visible);
      }
      if (empty) {
        empty.hidden = visible !== 0;
      }
    });

    toggles.forEach((toggle) => toggle.addEventListener('change', updateQuickAccess));
    updateQuickAccess();
  });
});
