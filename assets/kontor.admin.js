document.addEventListener('DOMContentLoaded', () => {
  const fieldGuidance = (control, label) => {
    const name = (control.name || '').toLowerCase();
    const normalizedLabel = label.replace(/\s*\*\s*$/, '').trim().toLowerCase() || 'this value';
    const exact = {
      name: ['A clear internal name teammates can recognize in lists and selections.', 'Use a concise, distinctive name.'],
      title: ['The title shown to teammates and, where applicable, in customer-facing output.', 'Lead with the subject or outcome, not an internal code.'],
      description: ['The context teammates need to understand the purpose and scope of this record.', 'Keep it concise; include details that affect execution or decisions.'],
      notes: ['Internal context that helps teammates work with this record.', 'Do not include passwords, secrets or unnecessary personal data.'],
      status: ['Controls whether this record is available to active workflows and selections.', 'Choose an inactive or archived state instead of deleting business history.'],
      email: ['The primary address used for communication and account matching.', 'Use a complete address such as name@example.com.'],
      email_address: ['The mailbox address used to send or receive messages.', 'Use a complete address such as team@example.com.'],
      phone: ['The main telephone number for this record.', 'Include the international country code when the number is used across regions.'],
      mobile: ['A direct mobile number for time-sensitive communication.', 'Include the international country code when possible.'],
      url: ['The complete web address used by this integration or resource.', 'Include https:// and verify that the destination is accessible.'],
      website: ['The organization’s primary public website.', 'Include the full address, for example https://example.com.'],
      currency: ['The currency used to interpret monetary values in this form.', 'Use the three-letter ISO code, for example EUR or USD.'],
      currency_code: ['The currency used to interpret monetary values in this record.', 'Use the three-letter ISO code, for example EUR or USD.'],
      quantity: ['The number of units included in this operation.', 'Use a positive number; decimals are allowed when the unit supports them.'],
      unit_price: ['The price for one unit before tax.', 'Enter a decimal amount without a currency symbol, for example 129.90.'],
      tax_rate: ['The percentage of tax applied to the taxable amount.', 'Enter the percentage as a number, for example 19.'],
      query: ['The GraphQL operation to validate and run against Kontor.', 'Request only the fields you need and keep query complexity within the stated limit.'],
      token: ['The credential that authorizes this request.', 'Treat tokens as secrets; they are not displayed again after creation.'],
      payload_json: ['The structured JSON payload processed by this operation.', 'Use valid JSON with double-quoted property names.'],
      raw_email: ['The complete raw email including headers and message body.', 'Keep a blank line between the headers and the body.'],
      subject: ['The concise subject recipients will see in their mailbox.', 'Describe the purpose or requested action in plain language.'],
      body_text: ['The plain-text content sent to recipients.', 'Include the context and next action the recipient needs.'],
      password: ['The password used to authenticate this account.', 'Use at least 12 characters and do not reuse another account’s password.'],
      code: ['A stable short code used in references, imports and integrations.', 'Use a concise value that will not need to change later.'],
      sku: ['Your internal stock-keeping code for search, imports and integrations.', 'Use a unique, stable code.'],
      barcode: ['The scannable identifier supplied by the manufacturer or your organization.', 'Enter digits exactly as printed, without spaces.'],
      dashboard_headline: ['The personal phrase shown prominently at the top of your Dashboard.', 'Keep it short enough to read at a glance; up to 90 characters.'],
      dashboard_message: ['Optional supporting context shown directly below your headline.', 'Use up to 180 characters, or leave blank for a headline-only intro.'],
    };

    let description = exact[name]?.[0];
    let note = exact[name]?.[1];

    if (!description && (name.endsWith('_uid') || name.endsWith('_id'))) {
      description = `Selects the ${normalizedLabel} connected to this record.`;
      note = 'Choose an existing record; the relationship is saved with this operation.';
    } else if (!description && (name.includes('date') || name.endsWith('_at') || name.startsWith('valid_'))) {
      description = `Sets when ${normalizedLabel} applies to this record.`;
      note = control.type === 'datetime-local' ? 'Use your local date and time.' : 'Use the date picker or YYYY-MM-DD.';
    } else if (!description && (name.includes('price') || name.includes('amount') || name.includes('rate'))) {
      description = `Records the numeric value for ${normalizedLabel}.`;
      note = 'Enter a number without a currency or percent symbol unless the label says otherwise.';
    } else if (!description && name.includes('json')) {
      description = `Defines the structured data used for ${normalizedLabel}.`;
      note = 'Use valid JSON with double-quoted property names.';
    } else if (!description && control.tagName === 'SELECT') {
      description = `Choose the option that controls ${normalizedLabel} for this record.`;
      note = control.required ? 'A selection is required before saving.' : 'Leave the default when no special value applies.';
    } else if (!description && control.tagName === 'TEXTAREA') {
      description = `Add the context teammates need for ${normalizedLabel}.`;
      note = 'Keep it concise and avoid secrets or unnecessary personal data.';
    } else if (!description && control.type === 'checkbox') {
      description = `Turns ${normalizedLabel} on or off for this operation.`;
      note = 'Review the setting before submitting the form.';
    } else if (!description) {
      description = `The value used for ${normalizedLabel} in this record and its connected workflows.`;
      note = control.required
        ? 'Required. Review this value before saving.'
        : 'Optional. Leave blank when the information is not known or does not apply.';
    }

    return { description, note };
  };

  const addNativeFieldGuidance = (workspace) => {
    workspace.querySelectorAll('.kontor-nativefield').forEach((field, fieldIndex) => {
      const control = field.querySelector('input:not([type="hidden"]), select, textarea');
      if (!control || control.type === 'submit' || control.type === 'button' || field.querySelector('.kontor-field-guidance')) {
        return;
      }

      const labelNode = field.querySelector(':scope > span');
      const label = labelNode?.textContent?.trim() || control.getAttribute('aria-label') || control.name;
      const guidance = fieldGuidance(control, label);
      const id = `kontor-help-${control.name || 'field'}-${fieldIndex}`;
      const help = document.createElement('span');
      help.className = 'kontor-field-guidance';
      help.id = id;
      const description = document.createElement('span');
      description.className = 'kontor-field-description';
      description.textContent = guidance.description;
      const note = document.createElement('span');
      note.className = 'kontor-field-note';
      const noteLabel = document.createElement('strong');
      noteLabel.textContent = 'Note:';
      note.append(noteLabel, ` ${guidance.note}`);
      help.append(description, note);
      field.append(help);

      const describedBy = control.getAttribute('aria-describedby');
      control.setAttribute('aria-describedby', describedBy ? `${describedBy} ${id}` : id);
    });
  };

  if (document.body.classList.contains('ProcessKontor')) {
    const contentBody = document.getElementById('pw-content-body');
    contentBody?.replaceWith(...contentBody.childNodes);
  }

  document.querySelectorAll('.ProcessKontor').forEach((workspace) => {
    addNativeFieldGuidance(workspace);
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

  document.querySelectorAll('[data-kontor-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
      const selector = button.dataset.kontorCopyTarget;
      const target = selector ? document.querySelector(selector) : null;

      if (!target) {
        return;
      }

      const value = target.value || target.textContent || '';
      try {
        await navigator.clipboard.writeText(value);
      } catch (error) {
        target.select?.();
        document.execCommand('copy');
      }
      const originalLabel = button.innerHTML;
      button.innerHTML = '<i class="fa fa-check"></i> Copied';
      window.setTimeout(() => {
        button.innerHTML = originalLabel;
      }, 2000);
    });
  });

  document.querySelectorAll('[data-kontor-api-token-form]').forEach((form) => {
    const accessModes = Array.from(form.querySelectorAll('[name="access_mode"]'));
    const scopes = Array.from(form.querySelectorAll('[data-kontor-api-scope]'));
    const restricted = () => form.querySelector('[name="access_mode"]:checked')?.value === 'restricted';

    const validateScopes = () => {
      if (scopes.length === 0) {
        return true;
      }

      const valid = !restricted() || scopes.some((scope) => scope.checked);
      scopes[0].setCustomValidity(valid ? '' : 'Choose at least one data permission.');

      return valid;
    };

    accessModes.forEach((mode) => mode.addEventListener('change', validateScopes));
    scopes.forEach((scope) => scope.addEventListener('change', validateScopes));
    form.addEventListener('submit', (event) => {
      if (!validateScopes()) {
        event.preventDefault();
        scopes[0]?.reportValidity();
        return;
      }

      if (!restricted() && !window.confirm('Issue a token with full access to current and future API resources?')) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-kontor-ledger-entry-form]').forEach((form) => {
    const debit = form.querySelector('[data-kontor-ledger-debit]');
    const credit = form.querySelector('[data-kontor-ledger-credit]');
    const selectedCurrency = (select) => select?.selectedOptions?.[0]?.dataset.currency || '';

    const validateAccounts = () => {
      if (!debit || !credit || debit.value === '' || credit.value === '') {
        return true;
      }

      let message = '';
      if (debit.value === credit.value) {
        message = 'Choose different debit and credit accounts.';
      } else if (selectedCurrency(debit) !== selectedCurrency(credit)) {
        message = 'Choose accounts that use the same currency.';
      }
      credit.setCustomValidity(message);

      return message === '';
    };

    debit?.addEventListener('change', validateAccounts);
    credit?.addEventListener('change', validateAccounts);
    form.addEventListener('submit', (event) => {
      if (!validateAccounts()) {
        event.preventDefault();
        credit?.reportValidity();
      }
    });
  });

  document.querySelectorAll('[data-kontor-ai-workbench]').forEach((workbench) => {
    const capability = workbench.querySelector('[data-kontor-ai-capability]');
    const groups = Array.from(workbench.querySelectorAll('[data-kontor-ai-fields]'));
    const update = () => {
      groups.forEach((group) => {
        const active = group.dataset.kontorAiFields === capability?.value;
        group.hidden = !active;
        group.querySelectorAll('input, select, textarea').forEach((control) => {
          control.disabled = !active;
          control.required = active && ['instructions', 'schema_fields'].includes(control.name);
        });
      });
    };

    capability?.addEventListener('change', update);
    update();
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

      if (form.hasAttribute('data-kontor-hide-empty')) {
        form.classList.toggle('uk-hidden', selected === 0);
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

  document.querySelectorAll('form[data-kontor-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.kontorConfirm)) {
        event.preventDefault();
      }
    });
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
