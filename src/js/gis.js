document.addEventListener('DOMContentLoaded', async () => {
    const navToggle = document.querySelector('.nav-toggle');
    const navigation = document.querySelector('.nav-panel');
    const navLinks = document.querySelectorAll('.nav-links a');
    const searchTrigger = document.querySelector('.search-trigger');
    const explorerToggle = document.querySelector('#toggleExplorer');
    const explorerLabel = explorerToggle.querySelector('.toggle-label');
    const closeExplorer = document.querySelector('#closeExplorer');
    const insightsToggle = document.querySelector('#toggleInsights');
    const closeInsights = document.querySelector('#closeInsights');
    const insightsPanel = document.querySelector('#gisInsights');
    const backdrop = document.querySelector('#gisBackdrop');
    const workspace = document.querySelector('.gis-map-shell');
    const mobileLayout = window.matchMedia('(max-width: 760px)');
    const dataModal = document.querySelector('#gisDataModal');
    const closeDataModal = document.querySelector('#closeDataModal');
    const dataModalBackdrop = document.querySelector('#gisDataModalBackdrop');

    function closeNavigation() {
        navigation?.classList.remove('open');
        navToggle?.setAttribute('aria-expanded', 'false');
        navToggle?.setAttribute('aria-label', 'Open navigation');
        const icon = navToggle?.querySelector('i');
        if (icon) icon.className = 'fa-solid fa-bars';
    }

    function setExplorer(open, returnFocus = false) {
        workspace.classList.toggle('explorer-open', open);
        document.getElementById('gisExplorer').inert = !open;
        explorerToggle.setAttribute('aria-expanded', String(open));
        explorerLabel.textContent = open ? 'Hide explorer' : 'Explore locations';
        if (open) setInsights(false);
        if (returnFocus) explorerToggle.focus();
    }

    function setInsights(open, returnFocus = false) {
        workspace.classList.toggle('insights-open', open);
        insightsPanel.inert = !open;
        insightsToggle.setAttribute('aria-expanded', String(open));
        if (open) setExplorer(false);
        if (returnFocus) insightsToggle.focus();
    }

    function setDataModal(open) {
        dataModal.hidden = !open;
        if (open) closeDataModal.focus();
    }

    navToggle?.addEventListener('click', () => {
        const open = navToggle.getAttribute('aria-expanded') !== 'true';
        navigation.classList.toggle('open', open);
        navToggle.setAttribute('aria-expanded', String(open));
        navToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        const icon = navToggle.querySelector('i');
        if (icon) icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
        setExplorer(false);
        setInsights(false);
    });
    navLinks.forEach(link => link.addEventListener('click', closeNavigation));
    searchTrigger?.addEventListener('click', () => {
        setExplorer(true);
        closeNavigation();
        window.setTimeout(() => document.querySelector('#gisSearch').focus(), 250);
    });
    explorerToggle.addEventListener('click', () => {
        setExplorer(explorerToggle.getAttribute('aria-expanded') !== 'true');
        closeNavigation();
    });
    closeExplorer.addEventListener('click', () => setExplorer(false, true));
    insightsToggle.addEventListener('click', () => setInsights(insightsToggle.getAttribute('aria-expanded') !== 'true'));
    closeInsights.addEventListener('click', () => setInsights(false, true));
    backdrop.addEventListener('click', () => {
        if (workspace.classList.contains('insights-open')) setInsights(false, true);
        else setExplorer(false, true);
    });
    closeDataModal.addEventListener('click', () => setDataModal(false));
    dataModalBackdrop.addEventListener('click', () => setDataModal(false));
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        if (!dataModal.hidden) { setDataModal(false); return; }
        if (navigation?.classList.contains('open')) { closeNavigation(); navToggle.focus(); }
        if (workspace.classList.contains('insights-open')) setInsights(false, true);
        if (workspace.classList.contains('explorer-open')) setExplorer(false, true);
    });
    document.addEventListener('click', event => {
        if (!event.target.closest('.site-header')) closeNavigation();
    });
    mobileLayout.addEventListener('change', () => {
        closeNavigation();
        setExplorer(false);
        setInsights(false);
    });
    setExplorer(false);
    setInsights(false);

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
        const map = L.map('gisMap', {
            preferCanvas: true,
            minZoom: 8,
            maxZoom: 19,
            zoomControl: false,
            scrollWheelZoom: !document.body.classList.contains('gis-embedded')
        });
        const boundaryPane = map.createPane('boundaryPane');
        const selectionPane = map.createPane('selectionPane');
        const availabilityPane = map.createPane('availabilityPane');
        Object.assign(boundaryPane.style, { zIndex: '410', pointerEvents: 'auto' });
        Object.assign(selectionPane.style, { zIndex: '430', pointerEvents: 'none' });
        Object.assign(availabilityPane.style, { zIndex: '450', pointerEvents: 'auto' });
        L.control.zoom({ position: 'bottomright' }).addTo(map);
        const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            tileSize: 256,
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        tiles.on('tileerror', () => { status.textContent = 'Background map unavailable. Boundary and data layers remain usable.'; });
        L.control.scale({ imperial: false }).addTo(map);

        const datasets = await Promise.all(['boundary', 'municipalities', 'barangays'].map(async name => {
            const response = await fetch(`${window.SBCIS_MAP_BASE || '../'}src/qgis/southern_leyte_${name}.geojson`);
            if (!response.ok) throw new Error(`Unable to load ${name} boundaries. Please retry.`);
            const data = await response.json();
            if (data.type !== 'FeatureCollection' || !data.features?.length) throw new Error(`The ${name} boundary file is empty or invalid.`);
            return data;
        }));
        const [province, municipalities, barangays] = datasets;
        const directoryResponse = await fetch((window.SBCIS_MAP_BASE || '../') + 'app/Controllers/locations.php');
        if (!directoryResponse.ok) throw new Error('Location directory unavailable. Please retry.');
        const directory = await directoryResponse.json();
        const municipalitiesByCode = new Map(directory.municipalities.map(row => [row.code, row]));
        const barangaysByCode = new Map(directory.barangays.map(row => [row.code, row]));
        const municipalitiesByBoundary = new Map(directory.municipalities.filter(row => row.boundaryId).map(row => [row.boundaryId, row]));
        const barangaysByBoundary = new Map(directory.barangays.filter(row => row.boundaryId).map(row => [row.boundaryId, row]));
        document.getElementById('locationSource').textContent = 'PSGC location directory' +
            (directory.status === 'live' ? '' : ' - saved copy') + ' | Updated ' + new Date(directory.fetchedAt).toLocaleDateString();
        const municipalityById = new Map(municipalities.features.map(feature => [feature.properties.GID_2, feature]));
        const barangayById = new Map(barangays.features.map(feature => [feature.properties.GID_3, feature]));
        const municipalityStyle = { color: '#176b4d', weight: 1.8, fillColor: '#75b590', fillOpacity: .035 };
        const barangayStyle = { color: '#4c8aa5', weight: 1, fillColor: '#8cc7dd', fillOpacity: .035 };
        const provinceLayer = L.geoJSON(province, {
            pane: 'boundaryPane', interactive: false,
            style: { color: '#0b3d2e', weight: 3.2, opacity: .95, fill: false }
        }).addTo(map);
        const municipalityLayer = L.geoJSON(municipalities, {
            pane: 'boundaryPane',
            style: municipalityStyle,
            onEachFeature: (feature, layer) => {
                const label = document.createElement('span');
                label.textContent = feature.properties.NAME_2;
                layer.bindTooltip(label);
                layer.on('click', () => selectBoundary(feature, 'municipality'));
                layer.on('mouseover', () => layer.setStyle({ weight: 2.8, fillOpacity: .13 }));
                layer.on('mouseout', () => layer.setStyle({
                    ...municipalityStyle,
                    fillOpacity: municipalitySelect.value && municipalitiesByCode.get(municipalitySelect.value)?.boundaryId !== feature.properties.GID_2 ? .015 : .035
                }));
            }
        }).addTo(map);
        const barangayLayer = L.geoJSON(null, {
            pane: 'boundaryPane',
            style: barangayStyle,
            onEachFeature: (feature, layer) => {
                const label = document.createElement('span');
                label.textContent = `${feature.properties.NAME_3}, ${feature.properties.NAME_2}`;
                layer.bindTooltip(label);
                layer.on('click', () => selectBoundary(feature, 'barangay'));
                layer.on('mouseover', () => layer.setStyle({ weight: 2.1, fillOpacity: .13 }));
                layer.on('mouseout', () => layer.setStyle(barangayStyle));
            }
        }).addTo(map);
        const selection = L.geoJSON(null, {
            pane: 'selectionPane', interactive: false,
            style: { color: '#d17a12', weight: 3.2, fillColor: '#f4bd63', fillOpacity: .16 }
        }).addTo(map);
        const availabilityLayer = L.layerGroup().addTo(map);

        const boreholes = Array.isArray(window.SBCIS_BOREHOLES) ? window.SBCIS_BOREHOLES : [];
        document.getElementById('visibleBoreholeCount').textContent = String(boreholes.length);
        const dataKey = document.getElementById('gisDataKey');
        dataKey.hidden = boreholes.length === 0;
        dataKey.classList.toggle('is-sample', Boolean(window.SBCIS_INTERPOLATION_PREVIEW));
        let selectedDataFeature = null;
        let selectedDataType = null;

        function formatMetric(value, suffix) {
            if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return 'Not recorded';
            return `${Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 })} ${suffix}`;
        }

        function average(values) {
            const numeric = values.map(Number).filter(Number.isFinite);
            return numeric.length ? numeric.reduce((sum, value) => sum + value, 0) / numeric.length : null;
        }

        function pointInRing(longitude, latitude, ring) {
            let inside = false;
            for (let index = 1; index < ring.length; index++) {
                const [ax, ay] = ring[index - 1];
                const [bx, by] = ring[index];
                const cross = (longitude - ax) * (by - ay) - (latitude - ay) * (bx - ax);
                if (Math.abs(cross) <= 1e-12 && longitude >= Math.min(ax, bx) && longitude <= Math.max(ax, bx) &&
                    latitude >= Math.min(ay, by) && latitude <= Math.max(ay, by)) return true;
                if ((ay > latitude) !== (by > latitude) && longitude < (bx - ax) * (latitude - ay) / (by - ay) + ax) inside = !inside;
            }
            return inside;
        }

        function pointInFeature(longitude, latitude, feature) {
            const geometry = feature?.geometry || feature;
            if (!geometry || !['Polygon', 'MultiPolygon'].includes(geometry.type)) return false;
            const polygons = geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
            return polygons.some(polygon => pointInRing(longitude, latitude, polygon[0]) &&
                !polygon.slice(1).some(hole => pointInRing(longitude, latitude, hole)));
        }

        function representativePoint(geometry) {
            const polygons = geometry?.type === 'Polygon' ? [geometry.coordinates] : (geometry?.coordinates || []);
            let ring = [];
            polygons.forEach(polygon => { if ((polygon[0] || []).length > ring.length) ring = polygon[0]; });
            if (!ring.length) return null;
            const total = ring.reduce((sum, position) => [sum[0] + Number(position[0]), sum[1] + Number(position[1])], [0, 0]);
            return [total[0] / ring.length, total[1] / ring.length];
        }

        function boreholesIn(feature) {
            return boreholes.filter(borehole => pointInFeature(
                Number(borehole.longitude), Number(borehole.latitude), feature
            ));
        }

        function availabilityPosition(feature, records) {
            const longitude = average(records.map(record => record.longitude));
            const latitude = average(records.map(record => record.latitude));
            if (longitude !== null && latitude !== null && pointInFeature(longitude, latitude, feature)) {
                return [latitude, longitude];
            }
            const point = representativePoint(feature.geometry);
            return point ? [point[1], point[0]] : null;
        }

        function renderAvailabilityMarkers(type, municipalityBoundaryId = null) {
            availabilityLayer.clearLayers();
            const features = type === 'barangay'
                ? barangays.features.filter(feature => feature.properties.GID_2 === municipalityBoundaryId)
                : municipalities.features;
            features.forEach(feature => {
                const records = boreholesIn(feature);
                if (!records.length) return;
                const position = availabilityPosition(feature, records);
                if (!position) return;
                const name = type === 'barangay'
                    ? (feature.properties?.NAME_3 || 'Barangay')
                    : (feature.properties?.NAME_2 || 'Municipality / city');
                const unit = records.length === 1 ? 'borehole' : 'boreholes';
                const icon = L.divIcon({
                    className: 'gis-data-marker-wrap',
                    html: `<span class="gis-data-availability-marker${window.SBCIS_INTERPOLATION_PREVIEW ? ' is-sample' : ''}"><i aria-hidden="true">✓</i><strong>${records.length}</strong></span>`,
                    iconSize: [34, 26],
                    iconAnchor: [17, 13]
                });
                const marker = L.marker(position, {
                    pane: 'availabilityPane', icon, keyboard: true, riseOnHover: true,
                    title: `${name}: ${records.length} ${unit} available`
                }).addTo(availabilityLayer);
                marker.bindTooltip(`${name}: ${records.length} ${unit}. Open area data.`, {
                    direction: 'top', offset: [0, -10]
                });
                marker.on('click', () => selectBoundary(feature, type));
            });
        }

        function estimatedValueFor(feature, type) {
            const active = window.SBCIS_ACTIVE_INTERPOLATION;
            const surfaceFeatures = active?.surface?.features;
            if (!Array.isArray(surfaceFeatures)) return null;
            const property = type === 'barangay' ? 'GID_3' : 'GID_2';
            const identifier = feature.properties?.[property];
            let matches = identifier ? surfaceFeatures.filter(surface => surface.properties?.[property] === identifier) : [];
            if (!matches.length) {
                matches = surfaceFeatures.filter(surface => {
                    const point = representativePoint(surface.geometry);
                    return point && pointInFeature(point[0], point[1], feature);
                });
            }
            return average(matches.map(surface => surface.properties?.value));
        }

        function recordCard(borehole) {
            const article = document.createElement('article');
            article.className = 'gis-modal-record';
            const header = document.createElement('div');
            header.className = 'gis-modal-record-header';
            const title = document.createElement('strong');
            title.textContent = borehole.borehole_code || 'Unnamed borehole';
            const coordinates = document.createElement('span');
            coordinates.textContent = Number(borehole.latitude).toFixed(5) + ', ' + Number(borehole.longitude).toFixed(5);
            header.append(title, coordinates);
            const place = document.createElement('p');
            place.textContent = [borehole.barangay_name, borehole.municipality_name].filter(Boolean).join(', ') || 'Area assigned from coordinates';
            const metadata = document.createElement('p');
            metadata.className = 'gis-modal-record-meta';
            metadata.textContent = 'Depth ' + formatMetric(borehole.borehole_depth_m, 'm') + ' · Elevation ' + formatMetric(borehole.elevation_m, 'm');
            const layers = document.createElement('div');
            layers.className = 'gis-modal-layer-list';
            borehole.layers.forEach(layer => {
                const row = document.createElement('div');
                const name = document.createElement('strong');
                name.textContent = 'Layer ' + layer.layer_number + ': ' + (layer.soil_type || 'Unrecorded soil type');
                const values = document.createElement('span');
                values.textContent = layer.depth_from_m + '–' + layer.depth_to_m + ' m · SPT ' +
                    (layer.spt_n_value ?? 'not recorded') + ' · ' + formatMetric(layer.bearing_capacity_kpa, 'kPa');
                row.append(name, values);
                layers.append(row);
            });
            article.append(header, place, metadata, layers);
            return article;
        }

        function renderAreaData(feature, type) {
            selectedDataFeature = feature;
            selectedDataType = type;
            const name = feature.properties?.NAME_3 || feature.properties?.NAME_2 || 'Selected area';
            const municipality = feature.properties?.NAME_2 || 'Southern Leyte';
            const matchingBoreholes = boreholesIn(feature);
            const layers = matchingBoreholes.flatMap(borehole => borehole.layers || []);
            const bearingAverage = average(layers.map(layer => layer.bearing_capacity_kpa));
            const sptAverage = average(layers.map(layer => layer.spt_n_value));
            const estimate = estimatedValueFor(feature, type);
            const active = window.SBCIS_ACTIVE_INTERPOLATION;

            document.getElementById('gisDataModalScope').textContent = type === 'barangay' ? 'BARANGAY DATA' : 'MUNICIPALITY / CITY DATA';
            document.getElementById('gisDataModalTitle').textContent = name;
            document.getElementById('gisDataModalLocation').textContent = type === 'barangay' ? municipality + ', Southern Leyte' : 'Southern Leyte';
            document.getElementById('gisDataModalBadge').textContent = window.SBCIS_INTERPOLATION_PREVIEW ? 'SAMPLE' : 'MEASURED';
            document.getElementById('gisDataModalSource').textContent = window.SBCIS_INTERPOLATION_PREVIEW ?
                'Synthetic sample observations assigned to this boundary for UI review.' :
                'Verified stored borehole observations whose coordinates fall inside this boundary.';
            document.getElementById('modalBoreholeCount').textContent = String(matchingBoreholes.length);
            document.getElementById('modalLayerCount').textContent = String(layers.length);
            document.getElementById('modalBearingAverage').textContent = bearingAverage === null ? '—' : formatMetric(bearingAverage, 'kPa');
            document.getElementById('modalSptAverage').textContent = sptAverage === null ? '—' : Number(sptAverage).toLocaleString(undefined, { maximumFractionDigits: 1 });
            document.getElementById('modalEstimatedValue').textContent = estimate === null ?
                'No approved estimated value is available for this area.' :
                formatMetric(estimate, active?.legend?.unit || '') + ' · ' + (active?.method || 'Interpolated surface');
            const records = document.getElementById('gisDataModalRecords');
            records.replaceChildren();
            matchingBoreholes.forEach(borehole => records.append(recordCard(borehole)));
            document.getElementById('gisDataModalEmpty').hidden = matchingBoreholes.length > 0;
        }

        function openAreaData(feature, type) {
            renderAreaData(feature, type);
            setDataModal(true);
        }

        window.addEventListener('sbcis:interpolation-updated', () => {
            if (selectedDataFeature && !dataModal.hidden) renderAreaData(selectedDataFeature, selectedDataType);
        });

        function setOptions(select, rows, placeholder) {
            select.replaceChildren(new Option(placeholder, ''));
            rows.forEach(row => select.add(new Option(row.name, row.code)));
        }

        function details(type, name, description) {
            document.querySelector('#locationType').textContent = type;
            document.querySelector('#locationName').textContent = name;
            document.querySelector('#locationDescription').textContent = description;
            document.querySelector('#insightAreaName').textContent = name;
            document.querySelector('#insightAreaType').textContent = type === 'PROVINCE' ? 'Province study boundary' : type.toLowerCase();
        }

        function fit(layer, maxZoom = 14) {
            const leftPadding = workspace.classList.contains('explorer-open') && !mobileLayout.matches ? 360 : 24;
            if (layer.getBounds().isValid()) map.fitBounds(layer.getBounds(), {
                paddingTopLeft: [leftPadding, 24], paddingBottomRight: [24, 24], maxZoom
            });
        }

        function selectBoundary(feature, type) {
            const record = type === 'municipality'
                ? municipalitiesByBoundary.get(feature.properties.GID_2)
                : barangaysByBoundary.get(feature.properties.GID_3);
            if (record) {
                type === 'municipality' ? selectMunicipality(record.code) : selectBarangay(record.code);
            } else {
                selection.clearLayers().addData(feature);
                fit(selection, type === 'barangay' ? 17 : 14);
                details('BOUNDARY', feature.properties.NAME_3 || feature.properties.NAME_2, 'This boundary has no confirmed PSGC directory match.');
            }
            openAreaData(feature, type);
        }

        function selectMunicipality(code, zoom = true, showDetails = false) {
            municipalitySelect.value = code;
            selection.clearLayers();
            barangayLayer.clearLayers();
            const record = municipalitiesByCode.get(code);
            const feature = record ? municipalityById.get(record.boundaryId) : null;
            const children = directory.barangays.filter(row => row.municipalityCode === code);
            setOptions(barangaySelect, children, record ? 'All barangays' : 'Select a municipality first');
            barangaySelect.disabled = !record;
            municipalityLayer.setStyle(candidate => ({
                ...municipalityStyle,
                fillOpacity: record && candidate.properties.GID_2 !== record.boundaryId ? .01 : .035
            }));
            if (!record) {
                renderAvailabilityMarkers('municipality');
                details('PROVINCE', 'Southern Leyte', directory.municipalities.length + ' municipalities / cities, ' + directory.barangays.length + ' barangays. Click an area to view data.');
                if (zoom) fit(provinceLayer);
                status.textContent = window.SBCIS_RECORDS_AVAILABLE === false ? 'Soil records unavailable. Location search remains available.' :
                    boreholes.length ? boreholes.length + ' source borehole record' + (boreholes.length === 1 ? '' : 's') + ' available through area details' :
                    'No verified boreholes are currently available within Southern Leyte';
                return;
            }
            if (feature) {
                selection.addData(feature);
                barangayLayer.addData({ type: 'FeatureCollection', features: barangays.features.filter(candidate => candidate.properties.GID_2 === record.boundaryId) });
                renderAvailabilityMarkers('barangay', record.boundaryId);
                if (zoom) fit(selection);
            } else {
                renderAvailabilityMarkers('municipality');
                if (zoom) fit(provinceLayer);
            }
            details('MUNICIPALITY / CITY', record.name, children.length + ' barangays. Click the municipality or a barangay boundary to view area data.');
            status.textContent = record.name + (feature ? ' selected — click the area for data' : ' — boundary unavailable');
            if (feature && showDetails) openAreaData(feature, 'municipality');
        }

        function selectBarangay(code, showDetails = false) {
            const record = barangaysByCode.get(code);
            if (!record) { selectMunicipality(municipalitySelect.value); return; }
            selectMunicipality(record.municipalityCode, false);
            barangaySelect.value = code;
            const feature = barangayById.get(record.boundaryId);
            if (feature) {
                selection.clearLayers().addData(feature).bringToFront();
                fit(selection, 17);
            } else {
                const parent = municipalityById.get(municipalitiesByCode.get(record.municipalityCode)?.boundaryId);
                fit(parent ? L.geoJSON(parent) : provinceLayer);
            }
            details('BARANGAY', record.name, record.municipalityName + ', Southern Leyte. Click the highlighted area to view data.');
            status.textContent = record.name + ' — ' + record.municipalityName + (feature ? ' selected' : ' (boundary unavailable)');
            if (feature && showDetails) openAreaData(feature, 'barangay');
        }

        function renderSearch() {
            results.replaceChildren();
            const term = search.value.trim().toLocaleLowerCase();
            if (!term) return;
            const matches = [...directory.municipalities, ...directory.barangays].filter(row =>
                (row.name + ' ' + (row.municipalityName || '') + ' ' + row.code).toLocaleLowerCase().includes(term));
            const count = document.createElement('p');
            count.textContent = matches.length ? matches.length + ' locations found' +
                (matches.length > 30 ? '; showing first 30. Refine your search.' : '.') : 'No locations found. Try another name.';
            results.append(count);
            matches.slice(0, 30).forEach(row => {
                const button = document.createElement('button');
                button.type = 'button';
                button.textContent = row.name + ' — ' + (row.municipalityName || 'Municipality / City');
                button.addEventListener('click', () => {
                    row.municipalityCode ? selectBarangay(row.code, true) : selectMunicipality(row.code, true, true);
                    results.replaceChildren();
                    search.value = '';
                    setExplorer(false, true);
                });
                results.append(button);
            });
        }

        setOptions(municipalitySelect, directory.municipalities, 'All municipalities / cities');
        municipalitySelect.disabled = search.disabled = reset.disabled = false;
        municipalitySelect.addEventListener('change', () => selectMunicipality(municipalitySelect.value, true, Boolean(municipalitySelect.value)));
        barangaySelect.addEventListener('change', () => {
            selectBarangay(barangaySelect.value, Boolean(barangaySelect.value));
            if (barangaySelect.value) setExplorer(false, true);
        });
        search.addEventListener('input', renderSearch);
        reset.addEventListener('click', () => {
            search.value = '';
            results.replaceChildren();
            setDataModal(false);
            selectMunicipality('');
        });
        new ResizeObserver(() => map.invalidateSize()).observe(document.querySelector('#gisMap'));
        try {
            if (!window.SbcisInterpolation) throw new Error('Estimate controls unavailable');
            new window.SbcisInterpolation(map, {
                base: window.SBCIS_MAP_BASE || '../',
                previewResult: window.SBCIS_INTERPOLATION_PREVIEW || null
            });
        } catch (error) {
            const estimateStatus = document.querySelector('#gisInterpolation [data-interpolation="status"]');
            if (estimateStatus) estimateStatus.textContent = 'Interpolated data is currently unavailable. The boundary map remains usable.';
            console.error('SBCIS interpolation initialization:', error);
        }
        selectMunicipality('');
        const query = new URLSearchParams(window.location.search);
        if (query.has('barangay')) selectBarangay(query.get('barangay'), true);
        else if (query.has('municipality')) selectMunicipality(query.get('municipality'), true, true);
        if (query.get('q')) { setExplorer(true); search.value = query.get('q'); renderSearch(); }
    } catch (error) {
        status.textContent = error.message || 'Unable to load the map. Please retry.';
        status.classList.add('is-error');
        document.querySelector('#locationDescription').textContent = 'Map data could not be loaded.';
        retry.hidden = false;
    }
});
