/**
 * Student & Parent Portal Interactive Scripts
 */

document.addEventListener('DOMContentLoaded', () => {
    // Universal Topbar Popovers (Notifications, Profile, Child Switcher)
    function closePopovers() {
        document.querySelectorAll('[data-popover]').forEach(button => {
            const panel = document.getElementById(button.dataset.popover);
            if (panel) panel.hidden = true;
            button.setAttribute('aria-expanded', 'false');
        });
    }

    document.querySelectorAll('[data-popover]').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const panel = document.getElementById(button.dataset.popover);
            if (!panel) return;
            const willOpen = panel.hidden;
            closePopovers();
            panel.hidden = !willOpen;
            button.setAttribute('aria-expanded', String(willOpen));
        });
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.popover-wrap')) closePopovers();
    });

    // Topbar Search for Portal Navigation Links
    const search = document.querySelector('#workspace-search');
    const input = search?.querySelector('input');
    const results = document.querySelector('#search-results');
    const navigation = [...document.querySelectorAll('.portal-nav-link, .nav-item')];

    function searchNavigation() {
        if (!input || !results) return;
        const query = input.value.trim().toLowerCase();
        results.replaceChildren();
        results.hidden = !query;
        if (!query) return;
        const matches = navigation.filter(item => item.textContent.toLowerCase().includes(query));
        matches.forEach(item => {
            const result = document.createElement('button');
            result.type = 'button';
            result.textContent = item.textContent.trim();
            result.addEventListener('click', () => {
                item.click();
                results.hidden = true;
                input.value = '';
            });
            results.append(result);
        });
        if (!matches.length) {
            const empty = document.createElement('p');
            empty.textContent = 'No matching portal modules.';
            results.append(empty);
        }
    }

    input?.addEventListener('input', searchNavigation);
    input?.addEventListener('focus', searchNavigation);
    search?.addEventListener('submit', event => {
        event.preventDefault();
        results?.querySelector('button')?.click();
    });
    document.addEventListener('click', event => {
        if (results && !event.target.closest('#workspace-search')) results.hidden = true;
    });

    // Mobile "More" drawer toggle
    const moreBtn = document.getElementById('portal-mobile-more-btn');
    const moreSheet = document.getElementById('portal-more-sheet');

    if (moreBtn && moreSheet) {
        moreBtn.addEventListener('click', (e) => {
            e.preventDefault();
            moreSheet.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!moreSheet.contains(e.target) && e.target !== moreBtn && !moreBtn.contains(e.target)) {
                moreSheet.classList.remove('open');
            }
        });
    }

    // Modal Helpers
    document.querySelectorAll('[data-portal-modal-target]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const targetId = trigger.getAttribute('data-portal-modal-target');
            const modal = document.getElementById(targetId);
            if (modal) modal.classList.add('open');
        });
    });

    document.querySelectorAll('[data-portal-modal-close]').forEach(closeBtn => {
        closeBtn.addEventListener('click', () => {
            const modal = closeBtn.closest('.portal-modal-backdrop');
            if (modal) modal.classList.remove('open');
        });
    });

    // Auto-dismiss toasts after 5 seconds
    document.querySelectorAll('.portal-toast[data-auto-dismiss]').forEach(toast => {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(10px)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    });
});
