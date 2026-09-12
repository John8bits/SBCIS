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
