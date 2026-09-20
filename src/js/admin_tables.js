// Server-paginated tables submit only the selected page size.
document.querySelectorAll('form[data-server-search] select[name="page_size"]').forEach(select => {
    select.addEventListener('change', () => select.form?.requestSubmit());
});

// Small, fixed reference directories can be filtered locally without another
// request. Reuse an explicit toolbar when the page provides one.
document.querySelectorAll('.panel > .table-wrap').forEach(wrap => {
    if (wrap.hasAttribute('data-server-paginated')) return;
    const table = wrap.querySelector('table');
    if (!table || table.classList.contains('layer-table') || !table.tBodies[0]) return;

    const rows = [...table.tBodies[0].rows];
    let page = 0;
    let toolbar = wrap.previousElementSibling;
    let search;
    let pageSize;
    let clear;

    if (toolbar?.matches('[data-local-table-toolbar]')) {
        search = toolbar.querySelector('[data-table-search]');
        pageSize = toolbar.querySelector('[data-table-page-size]');
        clear = toolbar.querySelector('[data-table-clear]');
    } else {
        toolbar = document.createElement('div');
        toolbar.className = 'table-toolbar';
        toolbar.dataset.localTableToolbar = '';

        const searchControl = document.createElement('div');
        searchControl.className = 'table-search-control';
        const label = document.createElement('label');
        label.textContent = 'Search records';
        const inputWrap = document.createElement('div');
        inputWrap.className = 'table-search-input';
        const icon = document.createElement('i');
        icon.className = 'fa-solid fa-magnifying-glass';
        icon.setAttribute('aria-hidden', 'true');
        search = document.createElement('input');
        search.type = 'search';
        search.maxLength = 100;
        search.placeholder = 'Type a name, code, or value';
        inputWrap.append(icon, search);
        searchControl.append(label, inputWrap);

        const actions = document.createElement('div');
        actions.className = 'table-toolbar-actions';
        const sizeLabel = document.createElement('label');
        sizeLabel.className = 'table-page-size';
        const sizeText = document.createElement('span');
        sizeText.textContent = 'Rows per page';
        pageSize = document.createElement('select');
        pageSize.setAttribute('aria-label', 'Rows per page');
        [10, 25, 50, 100].forEach(value => pageSize.add(new Option(String(value), String(value), false, value === 25)));
        sizeLabel.append(sizeText, pageSize);
        clear = document.createElement('button');
        clear.className = 'table-clear-button';
        clear.type = 'button';
        clear.textContent = 'Clear';
        clear.hidden = true;
        actions.append(sizeLabel, clear);
        toolbar.append(searchControl, actions);
        wrap.before(toolbar);
    }

    if (!search || !pageSize) return;
    const requestedSearch = new URLSearchParams(window.location.search).get('search');
    if (requestedSearch) search.value = requestedSearch;

    const footer = document.createElement('div');
    footer.className = 'table-footer';
    const status = document.createElement('span');
    status.setAttribute('aria-live', 'polite');
    const pages = document.createElement('div');
    pages.className = 'table-pages';
    const previous = document.createElement('button');
    previous.type = 'button';
    previous.textContent = 'Previous';
    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = 'Next';
    pages.append(previous, next);
    footer.append(status, pages);

    const empty = document.createElement('p');
    empty.className = 'table-no-results';
    empty.textContent = 'No matching locations. Try another search.';
    empty.hidden = true;
    wrap.after(empty, footer);

    function render() {
        const query = search.value.trim().toLocaleLowerCase();
        const size = Number.parseInt(pageSize.value, 10) || 25;
        const matching = rows.filter(row => row.textContent.toLocaleLowerCase().includes(query));
        const lastPage = Math.max(0, Math.ceil(matching.length / size) - 1);
        page = Math.min(page, lastPage);
        const start = page * size;
        const visible = new Set(matching.slice(start, start + size));
        rows.forEach(row => { row.hidden = !visible.has(row); });
        status.textContent = matching.length
            ? `${start + 1}–${Math.min(start + size, matching.length)} of ${matching.length} locations`
            : '0 locations';
        previous.disabled = page === 0;
        next.disabled = start + size >= matching.length;
        pages.hidden = matching.length <= size;
        empty.hidden = matching.length > 0;
        if (clear) clear.hidden = query === '';
    }

    search.addEventListener('input', () => { page = 0; render(); });
    pageSize.addEventListener('change', () => { page = 0; render(); });
    clear?.addEventListener('click', () => { search.value = ''; page = 0; search.focus(); render(); });
    previous.addEventListener('click', () => { page--; render(); });
    next.addEventListener('click', () => { page++; render(); });
    render();
});
