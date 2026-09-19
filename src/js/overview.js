const menu = document.getElementById('mobileMenu');
const sidebar = document.getElementById('sidebar');
menu?.addEventListener('click', () => {
    menu.setAttribute('aria-expanded', String(sidebar.classList.toggle('open')));
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
        sidebar.classList.remove('open');
        menu.setAttribute('aria-expanded', 'false');
    }
});
document.addEventListener('click', event => {
    if (!sidebar.contains(event.target) && !menu.contains(event.target)) {
        sidebar.classList.remove('open');
        menu.setAttribute('aria-expanded', 'false');
    }
});

const liveDate = document.querySelector('[data-live-date]');
const liveTime = document.querySelector('[data-live-time]');
if (liveDate && liveTime) {
    const dateFormatter = new Intl.DateTimeFormat(undefined, {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
    });
    const timeFormatter = new Intl.DateTimeFormat(undefined, {
        hour: 'numeric', minute: '2-digit', second: '2-digit'
    });
    const updateClock = () => {
        const now = new Date();
        liveDate.textContent = dateFormatter.format(now);
        liveDate.dateTime = now.toISOString().slice(0, 10);
        liveTime.textContent = timeFormatter.format(now);
        liveTime.dateTime = now.toTimeString().slice(0, 8);
    };
    updateClock();
    window.setInterval(updateClock, 1000);
}

document.querySelectorAll('[data-export-download]').forEach(link => {
    link.addEventListener('click', () => {
        const label = link.querySelector('span');
        if (!label) return;
        const original = link.dataset.downloadLabel || label.textContent;
        link.classList.add('is-preparing');
        label.textContent = 'Preparing…';
        window.setTimeout(() => {
            label.textContent = original;
            link.classList.remove('is-preparing');
        }, 2500);
    });
});
