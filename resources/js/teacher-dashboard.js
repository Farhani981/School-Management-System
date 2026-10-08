// Dashboard enhancement; all writes use the existing authorized attendance endpoint.
const dashboard = document.querySelector('[data-teacher-dashboard]');
if (dashboard) {
    const timezone = dashboard.dataset.timezone;
    let stale = false;
    const todayInSchool = () => new Intl.DateTimeFormat('en-CA', { timeZone: timezone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    const rows = () => [...dashboard.querySelectorAll('[data-register-key]')];
    const refreshHero = (pending, lowCount) => {
        dashboard.dataset.pending = String(pending);
        const pendingSummary = dashboard.querySelector('[data-pending-summary]');
        if (pendingSummary) pendingSummary.textContent = String(pending);
        const total = Number(dashboard.dataset.total);
        const marked = Math.max(0, total - pending);
        dashboard.querySelector('[data-marked-total]').textContent = marked;
        dashboard.querySelector('.td-progress').setAttribute('aria-valuenow', String(marked));
        dashboard.querySelector('.td-ring-fill').style.strokeDashoffset = String(314.159 * (1 - (total ? marked / total : 0)));
        dashboard.querySelector('[data-register-message]').textContent = !total ? (dashboard.dataset.emptyRegisterMessage || 'No first-period attendance assigned to you today.') : (pending ? `${pending} ${pending === 1 ? 'register is' : 'registers are'} waiting.` : 'All registers are marked.');
        dashboard.querySelector('[data-mark-first]').hidden = !pending;
        const need = dashboard.querySelector('[data-unmarked-need]');
        need.hidden = !pending;
        dashboard.querySelector('[data-unmarked-label]').textContent = `${pending} ${pending === 1 ? 'register' : 'registers'} not marked`;
        const low = dashboard.querySelector('[data-low-need]');
        low.hidden = !lowCount;
        dashboard.querySelector('[data-low-label]').textContent = `${lowCount} ${lowCount === 1 ? 'student' : 'students'} below 75% attendance`;
        dashboard.querySelector('[data-all-clear]').hidden = pending > 0 || lowCount > 0;
        const badge = document.querySelector('[data-attendance-badge]');
        if (badge) { badge.hidden = !pending; badge.textContent = pending; badge.setAttribute('aria-label', `${pending} unmarked registers`); }
    };
    const togglePanel = (row, open) => {
        const panel = row.querySelector('.td-quick-panel');
        if (!panel) return;
        panel.hidden = !open;
        row.querySelector('[data-quick-toggle]').setAttribute('aria-expanded', String(open));
        if (open) panel.querySelector('[data-all-present]').focus();
        else row.querySelector('[data-quick-toggle]').focus();
    };
    dashboard.addEventListener('click', event => {
        const row = event.target.closest('[data-register-key]');
        if (event.target.closest('[data-mark-first]')) {
            const first = rows().find(item => item.dataset.marked !== 'true' && item.querySelector('.td-quick-panel'));
            if (first) { togglePanel(first, true); first.scrollIntoView({ block: 'center', behavior: 'instant' }); }
        }
        if (row && event.target.closest('[data-quick-toggle]')) togglePanel(row, row.querySelector('.td-quick-panel')?.hidden);
        if (row && event.target.closest('[data-quick-cancel]')) togglePanel(row, false);
    });
    dashboard.querySelectorAll('.td-quick-panel').forEach(form => {
        let saving = false;
        const students = [...form.querySelectorAll('[data-student-id]')];
        const save = form.querySelector('[data-quick-save]');
        const error = form.querySelector('.td-quick-error');
        const summarize = () => {
            const counts = { present: 0, absent: 0, late: 0 };
            let selected = 0;
            students.forEach(student => { const input = student.querySelector('input[type=radio]:checked'); if (input) { counts[input.value]++; selected++; } });
            form.querySelector('[data-quick-summary]').textContent = `${counts.present} present \u00b7 ${counts.absent} absent \u00b7 ${counts.late} late${selected < students.length ? ` \u00b7 ${students.length - selected} unmarked` : ''}`;
            save.disabled = stale || saving || selected !== students.length;
        };
        form.addEventListener('change', summarize);
        form.querySelector('[data-all-present]').addEventListener('click', () => { form.querySelectorAll('input[value=present]').forEach(input => { input.checked = true; }); summarize(); });
        form.addEventListener('keydown', event => { if (event.key === 'Escape' && !saving) { event.preventDefault(); togglePanel(form.closest('[data-register-key]'), false); } });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (saving || stale || save.disabled || !form.reportValidity()) return;
            if (todayInSchool() !== dashboard.dataset.date) { checkClock(); return; }
            const body = new FormData(form);
            saving = true;
            error.hidden = true;
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('input,button').forEach(control => { control.disabled = true; });
            save.textContent = 'Saving...';
            try {
                const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                let data;
                try { data = await response.json(); } catch { throw new Error('Your session may have expired. Refresh this page before saving again.'); }
                if (!response.ok) {
                    const message = Object.values(data.errors || {}).flat().join(' ');
                    throw new Error(message || (response.status === 419 ? 'Your session expired. Refresh this page before saving again.' : data.message || 'Attendance could not be saved. Please try again.'));
                }
                if (data.marked !== true || !Number.isInteger(data.dashboard)) throw new Error('Attendance response could not be confirmed. Refresh to check the register.');
                const source = form.closest('[data-register-key]');
                rows().filter(row => row.dataset.registerKey === source.dataset.registerKey).forEach(row => {
                    row.dataset.marked = 'true';
                    const pill = row.querySelector('[data-register-status]');
                    if (!pill) return;
                    pill.classList.remove('td-pending'); pill.classList.add('td-done');
                    pill.textContent = `Marked \u00b7 ${data.present}/${data.total} present`;
                    const button = row.querySelector('[data-quick-toggle]');
                    button.textContent = 'Marked'; button.disabled = true; button.setAttribute('aria-expanded', 'false');
                    const panel = row.querySelector('.td-quick-panel'); if (panel) panel.hidden = true;
                });
                refreshHero(data.dashboard, data.lowAttendanceCount);
                dashboard.querySelector('[data-dashboard-status]').textContent = `Attendance saved. ${data.present} of ${data.total} present.`;
                dashboard.querySelector('[data-register-message]').setAttribute('tabindex', '-1');
                dashboard.querySelector('[data-register-message]').focus({ preventScroll: true });
            } catch (failure) {
                error.textContent = failure.message || 'Attendance could not be saved. Please try again.';
                error.hidden = false;
                form.hidden = false;
            } finally {
                saving = false;
                form.removeAttribute('aria-busy');
                form.querySelectorAll('input,button').forEach(control => { control.disabled = false; });
                save.textContent = 'Save attendance';
                summarize();
            }
        });
        summarize();
    });
    const checkClock = () => {
        const now = new Date();
        const date = todayInSchool();
        if (date !== dashboard.dataset.date) {
            stale = true;
            dashboard.querySelector('[data-register-message]').textContent = 'A new day has started. Refresh to see today\'s registers.';
            dashboard.querySelector('[data-mark-first]').hidden = true;
            dashboard.querySelectorAll('[data-quick-save]').forEach(button => { button.disabled = true; });
            return;
        }
        const hour = Number(new Intl.DateTimeFormat('en-GB', { timeZone: timezone, hour: 'numeric', hourCycle: 'h23' }).format(now));
        const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
        const node = dashboard.querySelector('[data-greeting]');
        node.textContent = node.textContent.replace(/^Good (morning|afternoon|evening)/, greeting);
        const periods = [...dashboard.querySelectorAll('[data-period-start]')];
        periods.forEach(row => {
            const active = now >= new Date(row.dataset.periodStart) && now < new Date(row.dataset.periodEnd);
            row.classList.toggle('is-now', active);
            const badge = row.querySelector('.td-now'); if (badge) badge.hidden = !active;
        });
        const headline = dashboard.querySelector('[data-day-headline]');
        if (!headline) return;
        const teaching = periods.filter(row => row.hasAttribute('data-register-key')).sort((a,b) => new Date(a.dataset.periodStart) - new Date(b.dataset.periodStart));
        const current = teaching.find(row => now >= new Date(row.dataset.periodStart) && now < new Date(row.dataset.periodEnd));
        const next = teaching.find(row => new Date(row.dataset.periodStart) > now);
        const minutes = next ? Math.max(1, Math.ceil((new Date(next.dataset.periodStart) - now) / 60000)) : 0;
        headline.textContent = current ? `${current.dataset.periodLabel} is on now.${next ? ` ${next.dataset.periodLabel} in ${minutes} min.` : ''}` : next ? `${next.dataset.periodLabel} starts in ${minutes} min.` : 'No more classes today.';
    };
    checkClock();
    setInterval(checkClock, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkClock(); });
}
