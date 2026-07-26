document.addEventListener('DOMContentLoaded', () => {
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

      const action = form.dataset.actionLabel || 'change';

      if (!window.confirm(`${action} ${selected} selected ${entityLabel}(s)?`)) {
        event.preventDefault();
      }
    });

    update();
  });
});
