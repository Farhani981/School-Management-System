// Enhance the existing select while retaining its submitted value and validation.
document.querySelectorAll('select[data-searchable-select]').forEach(select => {
    const wrapper = document.createElement('div');
    wrapper.className = 'library-select-search';
    select.before(wrapper);
    wrapper.append(select);

    const input = document.createElement('input');
    input.id = `${select.id}-search`;
    input.type = 'text';
    input.autocomplete = 'off';
    input.placeholder = select.dataset.searchPlaceholder || 'Select or search...';
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-expanded', 'false');

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'library-select-search-toggle';
    toggle.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
    toggle.setAttribute('aria-label', 'Show book options');
    toggle.tabIndex = -1;

    const menu = document.createElement('div');
    menu.id = `${select.id}-options`;
    menu.className = 'library-select-search-options';
    menu.setAttribute('role', 'listbox');
    menu.setAttribute('aria-label', 'Available books');
    menu.hidden = true;
    input.setAttribute('aria-controls', menu.id);

    const message = document.createElement('span');
    message.className = 'sr-only';
    message.setAttribute('role', 'status');
    wrapper.append(input, toggle, menu, message);
    document.querySelector(`label[for="${select.id}"]`)?.setAttribute('for', input.id);
    select.hidden = true;
    input.setAttribute('aria-required', String(select.required));

    let matches = [];
    let active = -1;
    const text = () => select.value ? select.selectedOptions[0]?.textContent.trim() || '' : '';
    const close = () => {
        menu.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    };
    const highlight = index => {
        active = index;
        [...menu.querySelectorAll('[role="option"]')].forEach((row, i) => row.classList.toggle('is-highlighted', i === active));
        const row = menu.querySelectorAll('[role="option"]')[active];
        if (row) {
            input.setAttribute('aria-activedescendant', row.id);
            row.scrollIntoView({ block: 'nearest' });
        } else input.removeAttribute('aria-activedescendant');
    };
    const choose = option => {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        input.removeAttribute('aria-invalid');
        input.focus();
        close();
    };
    const open = (query = '') => {
        matches = [...select.options].filter(option => option.value && !option.disabled && !option.hidden
            && `${option.textContent} ${option.dataset.search || ''}`.toLocaleLowerCase().includes(query.trim().toLocaleLowerCase()));
        menu.replaceChildren();
        matches.forEach((option, index) => {
            const row = document.createElement('div');
            row.id = `${menu.id}-${index}`;
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', String(option.value === select.value));
            row.textContent = option.textContent;
            row.addEventListener('pointerdown', event => event.preventDefault());
            row.addEventListener('click', () => choose(option));
            menu.append(row);
        });
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.className = 'library-select-search-empty';
            empty.textContent = 'No matching books found.';
            menu.append(empty);
        }
        message.textContent = `${matches.length} matching books.`;
        menu.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        highlight(matches.length ? 0 : -1);
    };
    select.addEventListener('change', () => {
        input.value = text();
        input.removeAttribute('aria-invalid');
        close();
    });
    select.addEventListener('invalid', event => {
        event.preventDefault();
        input.setAttribute('aria-invalid', 'true');
        input.focus();
        open(input.value);
        message.textContent = 'Choose a book from the matching options.';
    });
    input.addEventListener('focus', () => {
        open(select.value ? '' : input.value);
        if (select.value) input.select();
    });
    input.addEventListener('click', () => {
        if (menu.hidden) open(select.value ? '' : input.value);
    });
    input.addEventListener('input', () => {
        const query = input.value;
        if (select.value) {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
            input.value = query;
        }
        input.removeAttribute('aria-invalid');
        open(query);
    });
    input.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (menu.hidden) open(select.value ? '' : input.value);
            else if (matches.length) highlight((active + (event.key === 'ArrowDown' ? 1 : -1) + matches.length) % matches.length);
        } else if (event.key === 'Enter' && !menu.hidden) {
            event.preventDefault();
            if (matches[active]) choose(matches[active]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'Tab') close();
    });
    toggle.addEventListener('pointerdown', event => event.preventDefault());
    toggle.addEventListener('click', () => {
        if (!menu.hidden) close();
        else { input.focus(); open(''); }
    });
    document.addEventListener('pointerdown', event => { if (!wrapper.contains(event.target)) close(); });
    input.value = text();
});
