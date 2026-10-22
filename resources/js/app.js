import './bootstrap';
import './teacher-dashboard';
import './searchable-select';
import { marksPercentage } from './marks-percentage';

const periodEditForm = document.querySelector('#edit-period-form');
const periodCreateRetry = document.querySelector('#period-create-retry');
if (periodCreateRetry) {
    const values = JSON.parse(periodCreateRetry.textContent);
    const form = document.querySelector('#add-period-form');
    ['name', 'start_time', 'end_time', 'sort_order'].forEach(name => { form.elements[name].value = values[name] ?? ''; });
    ['is_break', 'is_active'].forEach(name => { form.elements[name].checked = Boolean(Number(values[name] || 0)); });
    document.querySelector('#add-period-modal').showModal();
}
if (periodEditForm) {
    const openPeriodEditor = (period, url) => {
        periodEditForm.action = url;
        periodEditForm.querySelector('#edit-period-id').value = period.id;
        periodEditForm.querySelector('#edit-p-name').value = period.name || '';
        periodEditForm.querySelector('#edit-p-start').value = (period.start_time || '').slice(0, 5);
        periodEditForm.querySelector('#edit-p-end').value = (period.end_time || '').slice(0, 5);
        periodEditForm.querySelector('#edit-p-sort').value = period.sort_order;
        periodEditForm.querySelector('#edit-p-break').checked = Boolean(period.is_break);
        periodEditForm.querySelector('#edit-p-active').checked = Boolean(period.is_active);
        document.querySelector('#edit-period-modal').showModal();
    };
    document.querySelectorAll('[data-edit-period]').forEach(button => {
        button.addEventListener('click', () => openPeriodEditor(JSON.parse(button.dataset.editPeriod), button.dataset.updateUrl));
    });
    const retry = document.querySelector('#period-edit-retry');
    if (retry) {
        const period = JSON.parse(retry.textContent);
        const button = [...document.querySelectorAll('[data-edit-period]')].find(item => String(JSON.parse(item.dataset.editPeriod).id) === String(period.id));
        if (button) openPeriodEditor(period, button.dataset.updateUrl);
    }
}
// Revalidate protected pages restored from the browser's back/forward snapshot.
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });

document.querySelectorAll('[data-fee-structure]').forEach(form => {
    const update = () => {
        const cents = [...form.querySelectorAll('[data-fee-amount]')].reduce((sum, input) => sum + Math.round((Number(input.value) || 0) * 100), 0);
        form.querySelector('[data-fee-total]').textContent = (cents / 100).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };
    form.addEventListener('input', update);
    update();
});
const feeDeleteDialog = document.querySelector('#fee-delete-dialog');
if (feeDeleteDialog) {
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-fee-delete]');
        if (!button) return;
        feeDeleteDialog.querySelector('[data-fee-delete-form]').action = button.dataset.feeDelete;
        feeDeleteDialog.querySelector('[data-fee-delete-message]').textContent = `Remove ${button.dataset.feeName}?`;
        feeDeleteDialog.showModal();
        feeDeleteDialog.querySelector('[data-fee-delete-cancel]').focus();
    });
    feeDeleteDialog.querySelector('[data-fee-delete-cancel]').addEventListener('click', () => feeDeleteDialog.close());
}
document.querySelectorAll('[data-fee-student-lookup]').forEach(panel => {
    const input = panel.querySelector('[data-fee-student-search]');
    const select = panel.querySelector('[data-fee-student-options]');
    const message = panel.querySelector('[data-fee-search-message]');
    let timer, pending, revision = 0;
    input.addEventListener('input', () => {
        clearTimeout(timer); pending?.abort();
        const request = ++revision;
        const query = input.value.trim();
        select.replaceChildren(new Option('Select student', ''));
        if (query.length < 2) { message.textContent = 'Type at least 2 characters.'; return; }
        message.textContent = 'Searching...';
        timer = setTimeout(async () => {
            pending = new AbortController();
            try {
                const url = new URL(panel.dataset.url, location.origin); url.searchParams.set('q', query);
                const response = await fetch(url, { signal: pending.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Search failed');
                const students = await response.json();
                if (request !== revision) return;
                students.forEach(student => select.append(new Option(student.label, student.id)));
                message.textContent = students.length ? 'Select a student from the results (up to 20 matches).' : 'No matching students.';
            } catch (error) {
                if (error.name !== 'AbortError' && request === revision) message.textContent = 'Unable to search. Please try again.';
            }
        }, 400);
    });
});

document.querySelectorAll('.attendance-mark-form').forEach(form => {
    const summary = () => {
        const counts = { present: 0, absent: 0, late: 0, leave: 0, half_day: 0 };
        form.querySelectorAll('[data-attendance-row]').forEach(row => {
            const input = row.querySelector('input[type="radio"]:checked, input[type="hidden"][name$="[status]"]');
            if (input && input.value in counts) counts[input.value]++;
        });
        form.querySelector('[data-attendance-summary]').textContent = Object.entries(counts).map(([status, count]) => `${status.replace('_', ' ')}: ${count}`).join(' · ');
    };
    form.addEventListener('change', summary);
    form.querySelectorAll('[data-mark-all]').forEach(button => button.addEventListener('click', () => {
        form.querySelectorAll(`input[type="radio"][value="${button.dataset.markAll}"]`).forEach(input => { input.checked = true; });
        summary();
    }));
    summary();
});
document.querySelector('[data-attendance-print]')?.addEventListener('click', () => window.print());

document.querySelectorAll('[data-class-subject-row]').forEach(row => {
    const checkbox = row.querySelector('[data-class-subject-select]');
    const sync = () => {
        row.querySelectorAll('[data-class-subject-value]').forEach(input => {
            input.disabled = !checkbox.checked;
            input.required = checkbox.checked && input.type !== 'checkbox';
        });
    };
    checkbox.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('[data-salary-section]').forEach(section => {
    const cents = name => Math.round((Number(section.querySelector(`[name="${name}"]`).value) || 0) * 100);
    const updateSalary = () => {
        const gross = ['basic_salary', 'house_allowance', 'medical_allowance', 'transport_allowance', 'other_allowance'].reduce((total, field) => total + cents(field), 0);
        const deductions = cents('fixed_deductions');
        section.querySelector('[data-salary-total="gross_salary"]').value = (gross / 100).toFixed(2);
        section.querySelector('[data-salary-total="net_salary"]').value = ((gross - deductions) / 100).toFixed(2);
        section.querySelector('[name="fixed_deductions"]').setCustomValidity(deductions > gross ? 'Fixed deductions must not exceed gross salary.' : '');
    };
    section.addEventListener('input', updateSalary);
    updateSalary();
});

document.querySelectorAll('[data-auto-filters]').forEach(form => {
    let timer;
    form.querySelectorAll('select, input[type="month"]').forEach(input => input.addEventListener('change', () => { clearTimeout(timer); form.requestSubmit(); }));
    form.querySelector('input[name="search"]')?.addEventListener('input', event => {
        clearTimeout(timer);
        if (!event.isComposing) timer = setTimeout(() => form.requestSubmit(), 500);
    });
});
document.querySelectorAll('[data-teacher-avatar]').forEach(photo => {
    const fallback = () => { photo.hidden = true; photo.nextElementSibling.hidden = false; };
    photo.addEventListener('error', fallback, { once: true });
    if (photo.complete && !photo.naturalWidth) fallback();
});

const body = document.body;
const sidebar = document.querySelector('#sidebar');
const toggle = document.querySelector('#menu-toggle');
const backdrop = document.querySelector('.drawer-backdrop');
const desktop = window.matchMedia(document.body.classList.contains('teacher-dashboard-page') ? '(min-width: 980px)' : '(min-width: 1024px)');

sidebar.querySelectorAll('.nav-item').forEach(item => {
    const label = item.querySelector('span:not(.active-dot)')?.textContent.trim();
    if (label) { item.setAttribute('aria-label', label); item.title = label; }
});
sidebar.querySelector('.brand').setAttribute('aria-label', 'School Management dashboard');
sidebar.querySelector('.brand').title = 'School Management dashboard';

function syncNavigation() {
    const open = desktop.matches ? !body.classList.contains('sidebar-collapsed') : body.classList.contains('drawer-open');
    toggle.setAttribute('aria-expanded', String(open));
    toggle.setAttribute('aria-label', open ? 'Collapse navigation to icons' : 'Expand navigation');
    sidebar.inert = body.classList.contains('teacher-dashboard-page') && !desktop.matches && !open;
    sidebar.querySelectorAll('[data-nav-toggle]').forEach(button => {
        document.getElementById(button.getAttribute('aria-controls')).inert = !open || button.getAttribute('aria-expanded') !== 'true';
    });
    backdrop.hidden = desktop.matches || !open;
}
function closeDrawer() {
    body.classList.remove('drawer-open');
    syncNavigation();
}
toggle.addEventListener('click', () => {
    body.classList.toggle(desktop.matches ? 'sidebar-collapsed' : 'drawer-open');
    syncNavigation();
    if (!desktop.matches && body.classList.contains('drawer-open')) sidebar.querySelector('a').focus();
});
backdrop.addEventListener('click', () => { closeDrawer(); toggle.focus(); });
desktop.addEventListener('change', closeDrawer);
syncNavigation();

let toastTimeout;
function showMessage(message) {
    const toast = document.querySelector('#ui-message');
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(toastTimeout);
    toastTimeout = setTimeout(() => { toast.hidden = true; }, 4500);
}
document.querySelectorAll('[data-module]').forEach(button => button.addEventListener('click', () => {
    showMessage(`${button.dataset.module} is not available yet.`);
    if (!desktop.matches) { closeDrawer(); toggle.focus(); }
}));
document.querySelectorAll('[data-message]').forEach(button => button.addEventListener('click', () => showMessage(button.dataset.message)));

function closePopovers() {
    document.querySelectorAll('[data-popover]').forEach(button => {
        document.getElementById(button.dataset.popover).hidden = true;
        button.setAttribute('aria-expanded', 'false');
    });
}
document.querySelectorAll('[data-popover]').forEach(button => button.addEventListener('click', () => {
    const panel = document.getElementById(button.dataset.popover);
    const willOpen = panel.hidden;
    closePopovers();
    panel.hidden = !willOpen;
    button.setAttribute('aria-expanded', String(willOpen));
}));
document.addEventListener('click', event => {
    if (!event.target.closest('.popover-wrap')) closePopovers();
    if (results && !event.target.closest('#workspace-search')) results.hidden = true;
});

const search = document.querySelector('#workspace-search');
const input = search?.querySelector('input');
const results = document.querySelector('#search-results');
const navigation = [...document.querySelectorAll('.nav-item, .portal-nav-link')];
function searchNavigation() {
    const query = input.value.trim().toLowerCase();
    results.replaceChildren();
    results.hidden = !query;
    if (!query) return;
    const matches = navigation.filter(item => item.textContent.toLowerCase().includes(query));
    matches.forEach(item => {
        const result = document.createElement('button');
        result.type = 'button';
        result.textContent = item.textContent.trim();
        result.addEventListener('click', () => { item.click(); results.hidden = true; input.value = ''; input.focus(); });
        results.append(result);
    });
    if (!matches.length) {
        const empty = document.createElement('p');
        empty.textContent = 'No matching school modules.';
        results.append(empty);
    }
}
input?.addEventListener('input', searchNavigation);
input?.addEventListener('focus', searchNavigation);
search?.addEventListener('submit', event => { event.preventDefault(); results.querySelector('button')?.click(); });
input?.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' && !results.hidden) { event.preventDefault(); results.querySelector('button')?.focus(); }
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        if (body.classList.contains('drawer-open')) { closeDrawer(); toggle.focus(); }
        closePopovers();
        if (results) results.hidden = true;
    }
    if (input && event.key === '/' && !event.target.matches('input, textarea, [contenteditable]')) { event.preventDefault(); input.focus(); }
    if (event.key === 'Tab' && body.classList.contains('drawer-open') && !desktop.matches) {
        const items = [...sidebar.querySelectorAll('a, button'), toggle].filter(item => item.getClientRects().length && !item.closest('[inert]'));
        const current = items.indexOf(document.activeElement);
        event.preventDefault();
        items[(current + (event.shiftKey ? -1 : 1) + items.length) % items.length].focus();
    }
});

// Calendar dates and events come from the controller; no business data is stored here.
const calendar = document.querySelector('#academic-calendar');
if (calendar) {
    const today = new Date(`${calendar.dataset.today}T12:00:00`);
    let events = JSON.parse(calendar.dataset.events);
    let upcoming = JSON.parse(calendar.dataset.upcoming || '[]');
    let requestNumber = 0;
    let displayed = new Date(today.getFullYear(), today.getMonth(), 1);
    let selected = null;
    const days = document.querySelector('#calendar-days');
    const eventList = document.querySelector('#calendar-events');
    const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

    function renderEvents() {
        eventList.replaceChildren();
        const matching = selected ? events.filter(event => event.date <= selected && (event.end_date || event.date) >= selected) : upcoming;
        if (!matching.length) {
            const empty = document.createElement('p');
            empty.className = 'muted';
            empty.textContent = selected ? 'No school events scheduled for this date.' : 'No upcoming events';
            eventList.append(empty);
        }
        matching.forEach(event => {
            const row = document.createElement('div');
            row.className = 'event-item';
            const dot = document.createElement('span');
            dot.className = `event-dot dot-${event.tone}`;
            const details = document.createElement('div');
            const title = document.createElement('strong');
            title.textContent = event.title;
            const description = document.createElement('p');
            description.textContent = `${new Date(`${event.date}T12:00:00`).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })} · ${event.detail}`;
            details.append(title, description);
            row.append(dot, details);
            eventList.append(row);
        });
    }
    function renderCalendar() {
        document.querySelector('#calendar-month').textContent = displayed.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
        days.replaceChildren();
        const offset = (displayed.getDay() + 6) % 7;
        for (let index = 0; index < 42; index++) {
            const date = new Date(displayed.getFullYear(), displayed.getMonth(), index - offset + 1);
            const key = dateKey(date);
            const matchingEvents = events.filter(item => item.date <= key && (item.end_date || item.date) >= key);
            const event = matchingEvents[0];
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'calendar-day';
            button.classList.toggle('outside', date.getMonth() !== displayed.getMonth());
            button.classList.toggle('today', key === dateKey(today));
            button.classList.toggle('selected', key === selected);
            button.textContent = date.getDate();
            button.setAttribute('aria-label', `${date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}${event ? `: ${event.title}` : ''}`);
            button.setAttribute('aria-pressed', String(key === selected));
            if (key === dateKey(today)) button.setAttribute('aria-current', 'date');
            matchingEvents.slice(0, 3).forEach(event => {
                const dot = document.createElement('span');
                dot.className = `event-dot dot-${event.tone}`;
                button.append(dot);
            });
            button.addEventListener('click', () => {
                selected = key;
                days.querySelectorAll('button').forEach(day => { day.classList.remove('selected'); day.setAttribute('aria-pressed', 'false'); });
                button.classList.add('selected');
                button.setAttribute('aria-pressed', 'true');
                renderEvents();
            });
            days.append(button);
        }
        renderEvents();
    }
    async function loadMonth() {
        selected = null;
        const currentRequest = ++requestNumber;
        events = [];
        renderCalendar();
        calendar.setAttribute('aria-busy', 'true');
        try {
            const url = new URL(calendar.dataset.url, window.location.origin);
            url.searchParams.set('month', dateKey(displayed).slice(0, 7));
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Calendar could not be loaded.');
            const data = await response.json();
            if (currentRequest !== requestNumber) return;
            events = data.events;
            upcoming = data.upcoming;
            renderCalendar();
        } catch (error) {
            if (currentRequest !== requestNumber) return;
            const message = document.createElement('p');
            message.className = 'muted';
            message.textContent = 'Unable to load calendar events. Please try again.';
            eventList.replaceChildren(message);
        } finally {
            if (currentRequest === requestNumber) calendar.removeAttribute('aria-busy');
        }
    }
    document.querySelector('#calendar-prev').addEventListener('click', () => { displayed.setMonth(displayed.getMonth() - 1); loadMonth(); });
    document.querySelector('#calendar-next').addEventListener('click', () => { displayed.setMonth(displayed.getMonth() + 1); loadMonth(); });
    document.querySelector('#calendar-today').addEventListener('click', () => { displayed = new Date(today.getFullYear(), today.getMonth(), 1); loadMonth(); });
    renderCalendar();
}

// Delete requires an explicit confirmation before submitting the CSRF-protected form.
const deleteDialog = document.querySelector('#delete-student-dialog');
if (deleteDialog) {
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-delete-student]');
        if (button) {
            document.querySelector('#delete-student-form').action = button.dataset.deleteStudent;
            document.querySelector('#delete-student-message').textContent = `Delete ${button.dataset.studentName}?`;
            deleteDialog.showModal();
            document.querySelector('#cancel-student-delete').focus();
        }
    });
    document.querySelector('#cancel-student-delete').addEventListener('click', () => deleteDialog.close());
}

function bindAvatarFallbacks() {
    document.querySelectorAll('[data-student-avatar]').forEach(photo => {
        const fallback = () => { photo.hidden = true; photo.nextElementSibling.hidden = false; };
        photo.addEventListener('error', fallback, { once: true });
        if (photo.complete && !photo.naturalWidth) fallback();
    });
}
bindAvatarFallbacks();

const studentFilters = document.querySelector('.student-filters');
if (studentFilters) {
    let timer;
    let pending;
    let revision = 0;
    const search = studentFilters.querySelector('[name="search"]');
    const invalidate = () => { clearTimeout(timer); pending?.abort(); revision++; };
    const apply = async () => {
        invalidate();
        const current = revision;
        pending = new AbortController();
        const url = new URL(studentFilters.action);
        new FormData(studentFilters).forEach((value, key) => { if (value || key === 'status') url.searchParams.set(key, value); });
        const panel = document.querySelector('.student-panel');
        panel.setAttribute('aria-busy', 'true');
        const skeleton = panel.querySelector('.directory-loading');
        if (skeleton) skeleton.hidden = false;
        const loadingMessage = panel.querySelector('.directory-load-status');
        if (loadingMessage) loadingMessage.textContent = 'Loading students…';
        try {
            const response = await fetch(url, { signal: pending.signal, headers: { Accept: 'text/html' } });
            if (!response.ok) throw new Error('Unable to load students');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (current !== revision) return;
            for (const selector of ['.student-table-scroll', '.student-pagination', '.student-filter-chips']) {
                const replacement = page.querySelector(selector);
                if (!replacement) throw new Error('Missing directory content');
                panel.querySelector(selector).replaceWith(replacement);
            }
            history.replaceState(null, '', url);
            bindAvatarFallbacks();
            updateStudentSelection();
            document.querySelectorAll('[data-export]').forEach(link => {
                const exportUrl = new URL(link.href);
                exportUrl.search = url.search;
                link.href = exportUrl;
            });
        } catch (error) {
            if (error.name !== 'AbortError' && current === revision) window.location.assign(url);
        } finally {
            if (current === revision) {
                panel.removeAttribute('aria-busy');
                if (skeleton) skeleton.hidden = true;
                if (loadingMessage) loadingMessage.textContent = 'Student list updated.';
            }
        }
    };
    studentFilters.addEventListener('submit', event => { event.preventDefault(); apply(); });
    studentFilters.querySelectorAll('select').forEach(select => select.addEventListener('change', () => {
        if (select.name === 'class_id') studentFilters.querySelector('[name="section_id"]').value = '';
        apply();
    }));
    document.addEventListener('click', event => {
        const link = event.target.closest('[data-sort]');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey) return;
        event.preventDefault();
        const sort = studentFilters.querySelector('[name="sort"]');
        const direction = studentFilters.querySelector('[name="direction"]');
        direction.value = sort.value === link.dataset.sort && direction.value === 'asc' ? 'desc' : 'asc';
        sort.value = link.dataset.sort;
        apply();
    });
    search.addEventListener('input', event => {
        invalidate();
        document.querySelector('.student-panel').removeAttribute('aria-busy');
        const skeleton = document.querySelector('.directory-loading');
        if (skeleton) skeleton.hidden = true;
        if (!event.isComposing) timer = setTimeout(apply, 400);
    });
    search.addEventListener('compositionend', () => { invalidate(); timer = setTimeout(apply, 400); });
}

// Student List controls are scoped to that page; existing panel navigation stays separate.
const directoryPage = document.querySelector('.student-list-page');
if (directoryPage) {
    directoryPage.addEventListener('click', event => {
        if (event.target.closest('[data-directory-print]')) {
            directoryPage.querySelector('.directory-export').open = false;
            window.print();
        }
        if (event.target.closest('[data-directory-clear]')) {
            directoryPage.querySelectorAll('[data-student-select]').forEach(input => { input.checked = false; });
            updateStudentSelection();
        }
        const bulk = event.target.closest('[data-directory-bulk]');
        if (bulk) {
            const action = directoryPage.querySelector('#bulk-action');
            action.value = bulk.dataset.directoryBulk;
            action.dispatchEvent(new Event('change'));
            if (action.value === 'id_cards') directoryPage.querySelector('#student-bulk-form').requestSubmit();
            else directoryPage.querySelector('#bulk-status').focus();
        }
        if (event.target.closest('[data-directory-promote]')) {
            const dialog = directoryPage.querySelector('#directory-promote-dialog');
            const selection = dialog.querySelector('[data-promote-selection]');
            selection.replaceChildren();
            directoryPage.querySelectorAll('[data-student-select]:checked').forEach(input => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden'; hidden.name = 'students[]'; hidden.value = input.value;
                selection.append(hidden);
            });
            dialog.showModal();
        }
        if (event.target.closest('[data-close-promote]')) directoryPage.querySelector('#directory-promote-dialog').close();
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.directory-filter-dropdown')) directoryPage.querySelector('.directory-filter-dropdown').open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') directoryPage.querySelector('.directory-filter-dropdown').open = false;
    });
    document.addEventListener('toggle', event => {
        const details = event.target;
        if (!details.matches('.directory-row-menu') || !details.open) return;
        const rect = details.querySelector('summary').getBoundingClientRect();
        const menu = details.querySelector('.dropdown-menu');
        menu.style.left = `${Math.max(8, Math.min(rect.right - 160, innerWidth - 168))}px`;
        menu.style.top = `${rect.bottom + 180 < innerHeight ? rect.bottom + 4 : Math.max(8, rect.top - 172)}px`;
    }, true);
    directoryPage.addEventListener('scroll', () => {
        directoryPage.querySelectorAll('.directory-row-menu[open]').forEach(menu => { menu.open = false; });
    }, true);
}

const photoInput = document.querySelector('#photo');
if (photoInput) {
    const preview = document.querySelector('#student-photo-preview');
    const placeholder = document.querySelector('#student-photo-placeholder');
    const originalSource = preview.getAttribute('src');
    const hideBrokenPhoto = () => { preview.hidden = true; placeholder.hidden = false; };
    preview.addEventListener('error', hideBrokenPhoto);
    if (preview.complete && !preview.naturalWidth) hideBrokenPhoto();
    let previewUrl;
    const syncInitials = () => {
        const first = document.querySelector('#first_name').value.trim();
        const last = document.querySelector('#last_name').value.trim();
        placeholder.textContent = ((Array.from(first)[0] ?? '') + (Array.from(last)[0] ?? '')).toLocaleUpperCase() || '?';
    };
    document.querySelector('#first_name').addEventListener('input', syncInitials);
    document.querySelector('#last_name').addEventListener('input', syncInitials);
    photoInput.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const file = photoInput.files[0];
        const valid = file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) && file.size <= 10 * 1024 * 1024;
        photoInput.setCustomValidity(file && !valid ? 'Choose a JPG, PNG or WebP photo up to 10 MB.' : '');
        document.querySelector('#photo-error').textContent = photoInput.validationMessage;
        if (valid) {
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
        } else if (originalSource) {
            preview.src = originalSource;
        } else {
            preview.removeAttribute('src');
        }
        preview.hidden = !valid && !originalSource;
        placeholder.hidden = !preview.hidden;
    });
}

function updateStudentSelection() {
    const selected = document.querySelectorAll('[data-student-select]:checked').length;
    const checkboxes = document.querySelectorAll('[data-student-select]');
    const all = document.querySelector('[data-select-all]');
    if (all) { all.checked = checkboxes.length > 0 && selected === checkboxes.length; all.indeterminate = selected > 0 && selected < checkboxes.length; }
    const count = document.querySelector('#selection-count');
    if (count) count.textContent = `${selected} selected`;
    const button = document.querySelector('#bulk-submit');
    if (button) button.disabled = selected === 0 || !document.querySelector('#bulk-action').value;
    const bar = document.querySelector('#student-bulk-form');
    if (bar) bar.hidden = selected === 0;
    document.querySelectorAll('[data-promotion-decision]').forEach(select => {
        select.disabled = !select.closest('tr').querySelector('[data-student-select]').checked;
    });
    syncPromotionTargets();
}
document.addEventListener('change', event => {
    if (event.target.matches('[data-select-all]')) {
        document.querySelectorAll('[data-student-select]').forEach(checkbox => { checkbox.checked = event.target.checked; });
    }
    if (event.target.matches('[data-select-all], [data-student-select]')) updateStudentSelection();
});
const bulkForm = document.querySelector('#student-bulk-form');
if (bulkForm) {
    const action = document.querySelector('#bulk-action');
    const syncAction = () => {
        document.querySelectorAll('[data-bulk-fields]').forEach(field => {
            const active = field.dataset.bulkFields === action.value;
            field.hidden = !active;
            field.querySelectorAll('input, select').forEach(input => { input.disabled = !active; input.required = active; });
        });
        updateStudentSelection();
    };
    action.addEventListener('change', syncAction);
    syncAction();
    bulkForm.addEventListener('submit', event => {
        if (!document.querySelector('[data-student-select]:checked')) { event.preventDefault(); showMessage('Select at least one student.'); return; }
    });
}

document.querySelectorAll('[data-nav-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const compact = desktop.matches ? body.classList.contains('sidebar-collapsed') : !body.classList.contains('drawer-open');
        if (compact) {
            body.classList.remove('sidebar-collapsed');
            if (!desktop.matches) body.classList.add('drawer-open');
        }
        const currentGroup = button.closest('.nav-group');
        const willExpand = compact || button.getAttribute('aria-expanded') !== 'true';

        // Accordion behavior: close other open dropdowns when expanding this one
        if (willExpand) {
            document.querySelectorAll('[data-nav-toggle]').forEach(otherBtn => {
                if (otherBtn !== button) {
                    otherBtn.setAttribute('aria-expanded', 'false');
                    const otherGroup = otherBtn.closest('.nav-group');
                    if (otherGroup) {
                        otherGroup.classList.remove('is-expanded');
                    }
                    const otherControls = otherBtn.getAttribute('aria-controls');
                    if (otherControls) {
                        const otherSubmenu = document.getElementById(otherControls);
                        if (otherSubmenu) otherSubmenu.inert = true;
                    }
                }
            });
        }

        button.setAttribute('aria-expanded', String(willExpand));
        currentGroup?.classList.toggle('is-expanded', willExpand);
        const submenu = document.getElementById(button.getAttribute('aria-controls'));
        if (submenu) submenu.inert = !willExpand;
        syncNavigation();
    });
});

document.addEventListener('toggle', event => {
    const details = event.target;
    if (!details.matches('[data-left-row-menu]') || !details.open) return;
    document.querySelectorAll('[data-left-row-menu][open]').forEach(other => {
        if (other !== details) other.open = false;
    });
    const rect = details.querySelector('summary').getBoundingClientRect();
    const menu = details.querySelector('.dropdown-menu');
    menu.style.left = `${Math.max(8, Math.min(rect.left - menu.offsetWidth - 6, innerWidth - menu.offsetWidth - 8))}px`;
    menu.style.top = `${Math.max(8, Math.min(rect.top, innerHeight - menu.offsetHeight - 8))}px`;
}, true);
const closeLeftRowMenus = () => document.querySelectorAll('[data-left-row-menu][open]').forEach(menu => { menu.open = false; });
document.addEventListener('scroll', closeLeftRowMenus, true);
window.addEventListener('resize', closeLeftRowMenus);

document.addEventListener('click', event => {
    document.querySelectorAll('.action-dropdown[open], .library-row-menu[open]').forEach(dropdown => {
        if (!dropdown.contains(event.target) || event.target.closest('.dropdown-menu a, .library-row-menu a')) dropdown.open = false;
    });
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') document.querySelectorAll('.action-dropdown[open], .library-row-menu[open]').forEach(dropdown => { dropdown.open = false; dropdown.querySelector('summary').focus(); });
});

function syncPromotionTargets() {
    const form = document.querySelector('#promotion-workflow-form');
    if (!form) return;
    const decisions = Array.from(form.querySelectorAll('[data-promotion-decision]:not(:disabled)')).map(input => input.value);
    form.querySelector('#target-session_id').required = decisions.some(action => ['promote', 'detain'].includes(action));
    for (const field of ['class_id', 'section_id']) form.querySelector(`#target-${field}`).required = decisions.includes('promote');
}
document.querySelectorAll('[data-promotion-decision]').forEach(select => select.addEventListener('change', syncPromotionTargets));
updateStudentSelection();

const confirmationForms = document.querySelectorAll('[data-confirm-action]');
if (confirmationForms.length) {
    const dialog = document.createElement('dialog');
    dialog.className = 'student-dialog';
    dialog.setAttribute('aria-labelledby', 'workflow-confirm-title');
    dialog.innerHTML = '<h2 id="workflow-confirm-title">Confirm action</h2><p data-confirm-message></p><div class="form-actions"><button type="button" class="button-secondary" data-confirm-cancel>Cancel</button><button type="button" class="button-primary" data-confirm-accept>Confirm</button></div>';
    document.body.append(dialog);
    let pendingForm;
    let accepted = false;
    confirmationForms.forEach(form => form.addEventListener('submit', event => {
        if (accepted) { accepted = false; return; }
        event.preventDefault();
        if (form.id === 'promotion-workflow-form' && !form.querySelector('[data-student-select]:checked')) { showMessage('Select at least one student.'); return; }
        pendingForm = form;
        dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirmAction;
        dialog.showModal(); dialog.querySelector('[data-confirm-cancel]').focus();
    }));
    dialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-confirm-accept]').addEventListener('click', () => { accepted = true; dialog.close(); pendingForm.requestSubmit(); });
}
document.querySelector('#student-promotion-form')?.addEventListener('submit', event => {
    if (!document.querySelector('[data-student-select]:checked')) { event.preventDefault(); showMessage('Select at least one student to promote.'); }
});

const profileTabs = Array.from(document.querySelectorAll('[data-profile-tab]'));
if (profileTabs.length) {
    const selectTab = (name, updateHash = false) => {
        if (!profileTabs.some(tab => tab.dataset.profileTab === name)) name = 'overview';
        profileTabs.forEach(tab => { const active = tab.dataset.profileTab === name; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1; });
        document.querySelectorAll('[data-profile-panel]').forEach(panel => { panel.hidden = panel.id !== name; });
        if (updateHash) history.replaceState(null, '', `#${name}`);
    };
    profileTabs.forEach((tab, index) => {
        tab.addEventListener('click', () => selectTab(tab.dataset.profileTab, true));
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % profileTabs.length;
            if (event.key === 'ArrowLeft') next = (index + profileTabs.length - 1) % profileTabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = profileTabs.length - 1;
            if (next !== undefined) { event.preventDefault(); profileTabs[next].focus(); selectTab(profileTabs[next].dataset.profileTab, true); }
        });
    });
    selectTab(location.hash.slice(1));
    window.addEventListener('hashchange', () => selectTab(location.hash.slice(1)));
}

document.querySelectorAll('.academic-quick-add[data-target]').forEach(panel => {
    const button = panel.querySelector('[data-save-option]');
    const nameInput = panel.querySelector('input');
    const message = panel.querySelector('[data-option-message]');
    nameInput.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); button.click(); }
    });
    button.addEventListener('click', async () => {
        const name = nameInput.value.trim();
        if (!name) { message.textContent = 'Please enter a name.'; nameInput.focus(); return; }
        button.disabled = true;
        message.textContent = 'Saving...';
        try {
            const response = await fetch(panel.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': panel.closest('form').querySelector('[name="_token"]').value },
                body: JSON.stringify({ type: panel.dataset.type, name }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat().join(' ') || data.message || 'Unable to save. Please try again.');
            const select = document.getElementById(panel.dataset.target);
            select.add(new Option(data.name, data.id, true, true));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            document.querySelector(`[data-empty-options="${panel.dataset.target}"]`)?.remove();
            message.textContent = `${data.name} added and selected.`;
            nameInput.value = '';
        } catch (error) {
            message.textContent = error.message;
        } finally { button.disabled = false; }
    });
});

document.querySelector('#use-father-guardian')?.addEventListener('click', () => {
    const father = document.querySelector('#father_name');
    if (!father.value.trim()) { father.focus(); showMessage('Enter the father name first.'); return; }
    document.querySelector('#guardian_name').value = father.value;
    document.querySelector('#guardian_relationship').value = 'Father';
    document.querySelector('#guardian_phone').value = document.querySelector('#phone').value;
    document.querySelector('#guardian_phone').focus();
    showMessage('Father selected as guardian. Please check the guardian phone.');
});


// Only included exam subjects require marks and a responsible teacher.
document.querySelectorAll('[data-exam-subject-row]').forEach(row => {
    const checkbox = row.querySelector('[data-exam-subject-select]');
    const sync = () => row.querySelectorAll('[data-exam-subject-value]').forEach(input => {
        input.disabled = checkbox.disabled || !checkbox.checked;
        input.required = !input.disabled;
    });
    checkbox.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('[data-formal-mark-row]').forEach(row => {
    const attendance = row.querySelector('[data-formal-attendance]');
    const marks = [...row.querySelectorAll('[data-formal-marks]')];
    const percentage = row.querySelector('[data-formal-percentage]');
    const updatePercentage = () => {
        if (!percentage) return;
        const valid = marks.every(input => input.validity.valid);
        const value = valid ? marksPercentage(marks.map(input => input.value), row.dataset.totalMarks, attendance.value) : null;
        percentage.textContent = value === null ? '—' : `${value.toFixed(2)}%`;
    };
    const sync = () => {
        marks.forEach(input => {
            input.readOnly = attendance.value === 'absent';
            if (input.readOnly) input.value = '';
        });
        updatePercentage();
    };
    marks.forEach(input => input.addEventListener('input', updatePercentage));
    attendance.addEventListener('change', sync);
    sync();
});

document.querySelectorAll('[data-library-select-all]').forEach(all => {
    const boxes=[...document.querySelectorAll('[data-library-select]')];
    all.addEventListener('change',()=>boxes.forEach(box=>{box.checked=all.checked;}));
    boxes.forEach(box=>box.addEventListener('change',()=>{const count=boxes.filter(b=>b.checked).length;all.checked=boxes.length>0&&count===boxes.length;all.indeterminate=count>0&&count<boxes.length;}));
});

document.querySelectorAll('[data-library-member-form]').forEach(form => {
    const type = form.querySelector('[data-member-type]');
    const limit = form.querySelector('[data-member-limit]');
    const defaults = JSON.parse(form.dataset.borrowingLimits);
    let previousType = type.value;
    const sync = () => form.querySelectorAll('[data-member-person]').forEach(field => {
        const active = field.dataset.memberPerson === type.value;
        field.hidden = !active;
        field.querySelector('select').disabled = !active;
        field.querySelector('select').required = active;
    });
    type.addEventListener('change', () => {
        if (!limit.value || Number(limit.value) === defaults[previousType]) limit.value = defaults[type.value];
        previousType = type.value;
        sync();
    });
    sync();
});

document.querySelectorAll('[data-library-issue-form]').forEach(form => {
    const member = form.querySelector('[data-issue-member]');
    const book = form.querySelector('[data-issue-book]');
    const copy = form.querySelector('[data-issue-copy]');
    const issue = form.querySelector('[data-issue-date]');
    const due = form.querySelector('[data-issue-due]');
    const syncCopies = () => {
        [...copy.options].forEach(option => {
            if (option.value) option.hidden = option.disabled = option.dataset.book !== book.value;
        });
        if (copy.selectedOptions[0]?.disabled) copy.value = '';
        copy.options[0].textContent = book.value ? 'Select an available copy' : 'Select a book first';
        const available = [...copy.options].filter(option => option.value && !option.disabled);
        const unavailable = Boolean(book.value) && available.length === 0;
        if (unavailable) copy.options[0].textContent = 'No available copies';
        form.querySelector('[data-issue-copy-help]').hidden = !unavailable;
        const addCopies = form.querySelector('[data-issue-add-copies]');
        addCopies.href = book.selectedOptions[0]?.dataset.addCopiesUrl || addCopies.href;
        if (!copy.value && available.length > 0) copy.value = available[0].value;
    };
    const syncDue = (replace = false) => {
        due.min = issue.value;
        const selected = member.selectedOptions[0];
        form.querySelector('[data-issue-member-help]').textContent = selected?.dataset.limit
            ? `${selected.dataset.issued} of ${selected.dataset.limit} books currently issued. Loan period: ${selected.dataset.loanDays} days.`
            : 'Choose an active member to see their borrowing allowance.';
        if (issue.value && selected?.dataset.loanDays && (replace || !due.value)) {
            const date = new Date(`${issue.value}T00:00:00Z`);
            if (!Number.isNaN(date.getTime())) {
                date.setUTCDate(date.getUTCDate() + Number(selected.dataset.loanDays));
                due.value = date.toISOString().slice(0, 10);
            }
        }
    };
    book.addEventListener('change', syncCopies);
    member.addEventListener('change', () => { syncDue(true); syncCopies(); });
    issue.addEventListener('input', () => syncDue(true));
    syncCopies();
    syncDue();
});

document.querySelectorAll('[data-schedule-editor]').forEach(form => {
    const cls=form.querySelector('[data-schedule-class]'), section=form.querySelector('[data-schedule-section]'), slot=form.querySelector('[data-schedule-slot]');
    const selectedSlot=slot.selectedOptions[0];
    if(selectedSlot?.dataset.class){cls.value=selectedSlot.dataset.class;section.value=selectedSlot.dataset.section;}
    const sync=()=>{
        [...section.options].forEach(o=>{if(o.value){o.hidden=o.dataset.class!==cls.value;o.disabled=o.hidden;}});
        if(section.selectedOptions[0]?.disabled)section.value='';
        [...slot.options].forEach(o=>{if(o.value){o.hidden=o.dataset.class!==cls.value||o.dataset.section!==section.value;o.disabled=o.hidden;}});
        if(slot.selectedOptions[0]?.disabled)slot.value='';
    };
    cls.addEventListener('change',sync);section.addEventListener('change',sync);sync();
    const start=form.querySelector('[data-schedule-start]'), end=form.querySelector('[data-schedule-end]'), duration=form.querySelector('[data-schedule-duration]');
    const calculate=()=>{const minutes=v=>{const [h,m]=v.split(':').map(Number);return h*60+m;};const diff=minutes(end.value)-minutes(start.value);duration.value=diff>0?`${Math.floor(diff/60)} hrs${diff%60?' '+diff%60+' min':''}`:'';};
    start.addEventListener('input',calculate);end.addEventListener('input',calculate);calculate();
});
