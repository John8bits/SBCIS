document.addEventListener('DOMContentLoaded', async () => {
    const navToggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('.nav-panel');
    const navLinks = document.querySelectorAll('.nav-links a');
    const searchTrigger = document.querySelector('.search-trigger');
    const explorerToggle = document.querySelector('#toggleExplorer');
    const explorerLabel = explorerToggle.querySelector('.toggle-label');
    const closeExplorer = document.querySelector('#closeExplorer');
    const backdrop = document.querySelector('#gisBackdrop');
    const workspace = document.querySelector('.gis-map-shell');
    const mobileLayout = window.matchMedia('(max-width: 760px)');
    function closeNavigation() {
        navigation.classList.remove('open');
        navToggle.setAttribute('aria-expanded', 'false');
        navToggle.setAttribute('aria-label', 'Open navigation');
        const icon = navToggle.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-bars';
    }
    function setExplorer(open, returnFocus = false) {
        workspace.classList.toggle('explorer-open', open);
        explorerToggle.setAttribute('aria-expanded', String(open));
        explorerLabel.textContent = open ? 'Hide explorer' : 'Explore locations';
        if (returnFocus) explorerToggle.focus();
    }
    navToggle.addEventListener('click', () => {
        const open = navToggle.getAttribute('aria-expanded') !== 'true';
        navigation.classList.toggle('open', open);
        navToggle.setAttribute('aria-expanded', String(open));
        navToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        const icon = navToggle.querySelector('i');
        if (icon) icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        setExplorer(false);
    });
    navLinks.forEach(link => link.addEventListener('click', closeNavigation));
    searchTrigger.addEventListener('click', () => {
        setExplorer(true);
        closeNavigation();
        window.setTimeout(() => document.querySelector('#gisSearch').focus(), 250);
    });
    explorerToggle.addEventListener('click', () => {
        setExplorer(explorerToggle.getAttribute('aria-expanded') !== 'true');
        closeNavigation();
    });
    closeExplorer.addEventListener('click', () => setExplorer(false, true));
    backdrop.addEventListener('click', () => setExplorer(false, true));
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (navigation.classList.contains('open')) { closeNavigation(); navToggle.focus(); }
        if (workspace.classList.contains('explorer-open')) setExplorer(false, true);
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.site-header')) closeNavigation();
    });
    mobileLayout.addEventListener('change', event => { closeNavigation(); setExplorer(!event.matches); });
    setExplorer(!mobileLayout.matches);
    const municipalitySelect = document.querySelector('#gisMunicipality');
    const barangaySelect = document.querySelector('#gisBarangay');
    const search = document.querySelector('#gisSearch');
    const results = document.querySelector('#gisResults');
    const status = document.querySelector('#gisStatus');
    const reset = document.querySelector('#resetMap');
    const retry = document.querySelector('#retryMap');
    retry.addEventListener('click', () => window.location.reload());
    try {
        if (!window.L) throw new Error('The map library could not load. Check your internet connection and retry.');
        const map = L.map('gisMap', { preferCanvas: true, minZoom: 8, maxZoom: 19, zoomControl: false });
        L.control.zoom({ position: 'topright' }).addTo(map);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            tileSize: 256,
            maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        tiles.on('tileerror', () => { status.textContent = 'Background map unavailable. You can still explore the boundaries.'; });
        L.control.scale({ imperial: false }).addTo(map);
        const datasets = await Promise.all(['boundary', 'municipalities', 'barangays'].map(async name => {
            const response = await fetch(`../src/qgis/southern_leyte_${name}.geojson`);
            if (!response.ok) throw new Error(`Unable to load ${name} boundaries. Please retry.`);
            const data = await response.json();
            if (data.type !== 'FeatureCollection' || !data.features?.length) throw new Error(`The ${name} boundary file is empty or invalid.`);
            return data;
        }));
        const [province, municipalities, barangays] = datasets;
        const municipalityById = new Map(municipalities.features.map(f => [f.properties.GID_2, f]));
        const barangayById = new Map(barangays.features.map(f => [f.properties.GID_3, f]));
        const municipalityStyle = { color: '#176b4d', weight: 2, fillColor: '#75b590', fillOpacity: .16 };
        const barangayStyle = { color: '#4788ac', weight: 1.3, fillColor: '#8cc7dd', fillOpacity: .12 };
        const provinceLayer = L.geoJSON(province, { interactive: false, style: { color: '#0b3d2e', weight: 3, fill: false } }).addTo(map);
        const municipalityLayer = L.geoJSON(municipalities, {
            style: municipalityStyle,
            onEachFeature: (feature, layer) => {
                const label = document.createElement('span');
                label.textContent = feature.properties.NAME_2;
                layer.bindTooltip(label);
                layer.on('click', () => selectMunicipality(feature.properties.GID_2));
                layer.on('mouseover', () => layer.setStyle({ weight: 3, fillOpacity: .28 }));
                layer.on('mouseout', () => layer.setStyle({
                    ...municipalityStyle,
                    fillOpacity: municipalitySelect.value && municipalitySelect.value !== feature.properties.GID_2 ? .04 : .16
                }));
            }
        }).addTo(map);
        const barangayLayer = L.geoJSON(null, {
            style: barangayStyle,
            onEachFeature: (feature, layer) => {
                const label = document.createElement('span');
                label.textContent = `${feature.properties.NAME_3}, ${feature.properties.NAME_2}`;
                layer.bindTooltip(label);
                layer.on('click', () => selectBarangay(feature.properties.GID_3));
                layer.on('mouseover', () => layer.setStyle({ weight: 2.5, fillOpacity: .28 }));
                layer.on('mouseout', () => layer.setStyle(barangayStyle));
            }
        }).addTo(map);
        const selection = L.geoJSON(null, { interactive: false, style: { color: '#d88516', weight: 3, fillColor: '#f3ba5a', fillOpacity: .3 } }).addTo(map);
        const sortBy = key => (a, b) => a.properties[key].localeCompare(b.properties[key]);
        function setOptions(select, features, key, label, placeholder) {
            select.replaceChildren(new Option(placeholder, ''));
            features.forEach(feature => select.add(new Option(feature.properties[label], feature.properties[key])));
        }
        function details(type, name, description) {
            document.querySelector('#locationType').textContent = type;
            document.querySelector('#locationName').textContent = name;
            document.querySelector('#locationDescription').textContent = description;
        }
        function fit(layer, maxZoom = 14) {
            map.fitBounds(layer.getBounds(), { padding: [24, 24], maxZoom });
        }
        function selectMunicipality(id, zoom = true) {
            municipalitySelect.value = id;
            selection.clearLayers();
            barangayLayer.clearLayers();
            const feature = municipalityById.get(id);
            const children = barangays.features.filter(f => f.properties.GID_2 === id).sort(sortBy('NAME_3'));
            setOptions(barangaySelect, children, 'GID_3', 'NAME_3', feature ? 'All barangays' : 'Select a municipality first');
            barangaySelect.disabled = !feature;
            municipalityLayer.setStyle(f => ({ ...municipalityStyle, fillOpacity: feature && f.properties.GID_2 !== id ? .04 : .16 }));
            if (!feature) {
                details('PROVINCE', 'Southern Leyte', `${municipalities.features.length} municipalities / cities · ${barangays.features.length} barangays. Select an area to explore its barangays.`);
                if (zoom) fit(provinceLayer);
                status.textContent = 'Click a municipality or choose one from the list.';
                return;
            }
            barangayLayer.addData({ type: 'FeatureCollection', features: children });
            details('MUNICIPALITY / CITY', feature.properties.NAME_2, `${children.length} barangays. Choose a barangay or click its boundary to zoom in.`);
            if (zoom) fit(L.geoJSON(feature));
            status.textContent = `${feature.properties.NAME_2} · ${children.length} barangays`;
        }
        function selectBarangay(id) {
            const feature = barangayById.get(id);
            if (!feature) { selectMunicipality(municipalitySelect.value); return; }
            if (municipalitySelect.value !== feature.properties.GID_2) selectMunicipality(feature.properties.GID_2, false);
            barangaySelect.value = id;
            selection.clearLayers().addData(feature).bringToFront();
            fit(selection, 17);
            details('BARANGAY', feature.properties.NAME_3, `${feature.properties.NAME_2}, Southern Leyte`);
            status.textContent = `${feature.properties.NAME_3} · ${feature.properties.NAME_2}`;
        }
        function renderSearch() {
            results.replaceChildren();
            const term = search.value.trim().toLocaleLowerCase();
            if (!term) return;
            const matches = [...municipalities.features, ...barangays.features].filter(f =>
                `${f.properties.NAME_3 || ''} ${f.properties.NAME_2}`.toLocaleLowerCase().includes(term));
            const count = document.createElement('p');
            count.textContent = matches.length ? `${matches.length} locations found${matches.length > 30 ? ' · Showing first 30; refine your search.' : '.'}` : 'No locations found. Try another name.';
            results.append(count);
            matches.slice(0, 30).forEach(feature => {
                const p = feature.properties;
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = p.GID_3 ? `${p.NAME_3} — ${p.NAME_2}` : `${p.NAME_2} — Municipality / City`;
                button.addEventListener('click', () => {
                    p.GID_3 ? selectBarangay(p.GID_3) : selectMunicipality(p.GID_2);
                    results.replaceChildren();
                    search.value = '';
                    setExplorer(false, true);
                });
                results.append(button);
            });
        }
        setOptions(municipalitySelect, [...municipalities.features].sort(sortBy('NAME_2')), 'GID_2', 'NAME_2', 'All municipalities / cities');
        municipalitySelect.disabled = search.disabled = reset.disabled = false;
        municipalitySelect.addEventListener('change', () => selectMunicipality(municipalitySelect.value));
        barangaySelect.addEventListener('change', () => {
            selectBarangay(barangaySelect.value);
            if (barangaySelect.value) setExplorer(false, true);
        });
        search.addEventListener('input', renderSearch);
        reset.addEventListener('click', () => { search.value = ''; results.replaceChildren(); selectMunicipality(''); setExplorer(false); });
        new ResizeObserver(() => map.invalidateSize()).observe(document.querySelector('#gisMap'));
        selectMunicipality('');
        const query = new URLSearchParams(window.location.search).get('q');
        if (query) { search.value = query; renderSearch(); }
    } catch (error) {
        status.textContent = error.message || 'Unable to load the map. Please retry.';
        status.classList.add('is-error');
        document.querySelector('#locationDescription').textContent = 'Map data could not be loaded.';
        retry.hidden = false;
    }
});
