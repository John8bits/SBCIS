// Local search and paging only affect the displayed table, never the full export.
document.querySelectorAll('.panel > .table-wrap').forEach(wrap => {
    const table = wrap.querySelector('table');
    if (!table || table.classList.contains('layer-table')) return;
    const rows = [...table.tBodies[0].rows];
    let page = 0;
    const size = 10;
    const toolbar = document.createElement('div'); toolbar.className = 'table-toolbar';
    const label = document.createElement('label'); label.append('Search records');
    const search = document.createElement('input'); search.type = 'search'; search.placeholder = 'Type a name, code, or value'; label.append(search); toolbar.append(label);
    const requestedSearch = new URLSearchParams(window.location.search).get('search');
    if (requestedSearch) search.value = requestedSearch;
    const footer = document.createElement('div'); footer.className = 'table-footer';
    const status = document.createElement('span'); status.setAttribute('aria-live', 'polite');
    const pages = document.createElement('div'); pages.className = 'table-pages';
    const previous = document.createElement('button'); previous.type = 'button'; previous.textContent = 'Previous';
    const next = document.createElement('button'); next.type = 'button'; next.textContent = 'Next'; pages.append(previous, next); footer.append(status, pages);
    const empty = document.createElement('p'); empty.className = 'table-no-results'; empty.textContent = 'No matching records. Try another search.'; empty.hidden = true;
    wrap.before(toolbar); wrap.after(empty, footer);
    function render() {
        const query = search.value.trim().toLocaleLowerCase();
        const matching = rows.filter(row => row.textContent.toLocaleLowerCase().includes(query));
        const start = page * size; const visible = new Set(matching.slice(start, start + size));
        rows.forEach(row => { row.hidden = !visible.has(row); });
        status.textContent = matching.length ? `${start + 1}–${Math.min(start + size, matching.length)} of ${matching.length} records` : '0 records';
        previous.disabled = page === 0; next.disabled = start + size >= matching.length; empty.hidden = matching.length > 0;
    }
    search.addEventListener('input', () => { page = 0; render(); });
    previous.addEventListener('click', () => { page--; render(); }); next.addEventListener('click', () => { page++; render(); }); render();
});
