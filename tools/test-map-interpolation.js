// Integration harness for the real map scripts. DOM/Leaflet are doubles, not a visual browser test.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
// Read the production palette, as the shared PHP map partial does.
const palette = JSON.parse(require('node:child_process').execFileSync('php', ['-r',
    'require "config/InterpolationConfig.php"; echo json_encode([Config\\InterpolationConfig::bearingCapacityClasses(), Config\\InterpolationConfig::UNKNOWN_BEARING_CAPACITY]);'
], { cwd: path.join(__dirname, '..'), encoding: 'utf8' }));
class Element {
    constructor() {
        this.value = ''; this.children = []; this.events = {}; this.attrs = {}; this.dataset = {}; this.style = {};
        const classes = new Set();
        this.classList = { contains: key => classes.has(key), add: key => classes.add(key), remove: key => classes.delete(key),
            toggle: (key, enabled) => { if (enabled) classes.add(key); else classes.delete(key); } };
        this.nodes = {};
    }
    querySelector(key) { return this.nodes[key] ||= new Element(); }
    addEventListener(key, fn) { const previous = this.events[key]; this.events[key] = (...args) => { previous?.(...args); return fn(...args); }; }
    removeEventListener(key) { delete this.events[key]; }
    setAttribute(key, value) { this.attrs[key] = value; }
    getAttribute(key) { return this.attrs[key]; }
    replaceChildren(...children) { this.children = children; this.value = children[0]?.value || ''; }
    add(child) { this.children.push(child); }
    append(...children) { this.children.push(...children); }
    contains(element) { return element === this || this.children.some(child => child.contains?.(element)); }
    focus() { this.focused = true; }
    fire(key) { this.events[key]?.({ target: this }); }
    dispatchEvent(event) { this.events[event.type]?.(event); }
}
async function testMap(mobile, base, capacity = 135, expectedColor = '#FF7043', expectedClass = 'Low', embedded = false) {
    const document = new Element(); document.body = new Element();
    if (embedded) document.body.classList.add('gis-embedded');
    document.getElementById = key => document.querySelector('#' + key);
    document.querySelectorAll = () => [];
    document.createElement = () => new Element();
    const window = new Element();
    const hoverTimers = new Map();
    let nextHoverTimer = 0;
    window.setTimeout = callback => { const id = ++nextHoverTimer; hoverTimers.set(id, callback); return id; };
    window.clearTimeout = id => hoverTimers.delete(id);
    const flushHoverTimers = () => { const callbacks = [...hoverTimers.values()]; hoverTimers.clear(); callbacks.forEach(callback => callback()); };
    window.matchMedia = () => ({ matches: mobile, addEventListener() {} });
    window.location = { search: '' }; window.SBCIS_MAP_BASE = base;
    document.getElementById('gisInterpolation').dataset.mode = base === '../../' ? 'admin' : 'public';
    document.getElementById('gisDataModal').hidden = true;
    document.getElementById('gisCapacityCard').hidden = true;
    window.SBCIS_BEARING_CAPACITY_CLASSES = palette[0];
    window.SBCIS_UNKNOWN_BEARING_CAPACITY = palette[1];
    window.SBCIS_BOREHOLES = [{ borehole_code: 'Fixture', latitude: 10.15, longitude:124.85,
        layers: capacity === null ? [] : capacity === 275
            ? [{ bearing_capacity_kpa: capacity, soil_classification: 'OH' }]
            : [{ bearing_capacity_kpa: capacity, layer_number: 3, soil_type: 'Clay', soil_classification: 'CL', depth_from_m: 0, depth_to_m: 2 },
                { bearing_capacity_kpa: 300, layer_number: 7, soil_type: 'Sand', soil_classification: 'SP', depth_from_m: 2, depth_to_m: 4 },
                { layer_number: 9, soil_type: '   ', soil_classification: null }] }];
    const layers = new Set(), markers = [], featureLayers = [];
    const makeLayer = () => ({ children: new Set(), addTo(parent) { layers.add(this); parent.children?.add(this); this.parent = parent; return this; },
        remove() { layers.delete(this); }, events: {},
        on(key, fn) { const previous = this.events[key]; this.events[key] = (...args) => { previous?.(...args); return fn(...args); }; return this; },
        bindTooltip() {},
        bindPopup(html, options) {
            this.popup = html;
            const element = new Element();
            this.popupObject = { options: { autoPan: true, ...options }, getElement: () => element };
            this.on('click', () => this.openPopup());
            return this;
        },
        getPopup() { return this.popupObject; },
        openPopup() { if (!this.popupOpen) { this.popupOpen = true; this.autoPanWhenOpened = this.popupObject.options.autoPan; this.events.popupopen?.(); } return this; },
        closePopup() { if (this.popupOpen) { this.popupOpen = false; this.events.popupclose?.(); } return this; },
        getLayers() { return [...this.children]; },
        getLatLng() { return Array.isArray(this.position) ? { lat: this.position[0], lng: this.position[1] } : this.position; },
        setLatLng(point) { this.position = point; return this; },
        setStyle() {}, clearLayers() { this.children.clear(); return this; }, addData() { return this; }, bringToFront() { return this; },
        getBounds: () => ({ isValid: () => true }) });
    const map = {
        fitCount: 0, zoom: 10, children: new Set(), events: {}, createPane: () => ({ style: {} }), once() {},
        getZoom() { return this.zoom; }, getBoundsZoom() { return 10; },
        addLayer(layer) { this.children.add(layer); }, removeLayer(layer) { this.children.delete(layer); },
        hasLayer(layer) { return this.children.has(layer); },
        getContainer: () => document.getElementById('gisMap'),
        project: point => ({ x: point.lng, y: point.lat }),
        distance(a, b) { return Math.hypot(a.lat - b.lat, a.lng - b.lng) * 111000; },
        on(name, handler) { this.events[name] = handler; return this; },
        fitBounds() { this.fitCount++; }, invalidateSize() {}
    };
    const geoJSON = (data, options = {}) => {
        const group = makeLayer();
        const addFeatures = payload => {
            for (const feature of payload?.features || []) {
                if (options.onEachFeature) {
                    const layer = makeLayer(); options.onEachFeature(feature, layer); featureLayers.push({ feature, layer });
                }
            }
        };
        group.addData = payload => { addFeatures(payload); return group; };
        addFeatures(data);
        return group;
    };
    const L = { map: (id, options) => { map.options = options; return map; }, tileLayer: makeLayer, geoJSON, layerGroup: makeLayer, canvas: () => ({}), DomEvent: { stopPropagation() {} },
        divIcon: options => options,
        latLng: (lat, lng) => ({ lat, lng }),
        polyline: (positions, options) => { const line = makeLayer(); line.positions = positions; line.options = options; return line; },
        marker: (position, options) => { const marker = makeLayer(); marker.position = position; marker.options = options; markers.push(marker); return marker; },
        circleMarker: (position, options) => { const marker = makeLayer(); marker.position = position; marker.options = options; markers.push(marker); return marker; },
        control: { zoom: makeLayer, scale: makeLayer } };
    window.L = L;
    const feature = { type: 'Feature', properties: { GID_2: 'm1', GID_3: 'b1', NAME_2: 'Maasin', NAME_3: 'Test Barangay' },
        geometry: { type:'Polygon', coordinates:[[[124.8,10.1],[124.9,10.1],[124.9,10.2],[124.8,10.2],[124.8,10.1]]] } };
    const directory = { status:'cached', fetchedAt:'2026-01-01', municipalities:[{code:'M',name:'Maasin',boundaryId:'m1'}],
        barangays:[{code:'B',name:'Test Barangay',boundaryId:'b1',municipalityCode:'M',municipalityName:'Maasin'}] };
    let endpointCalls = 0;
    const fetcher = async url => ({ ok: true, status:200, headers:{get:()=> 'application/json'}, json: async () => {
        if (url.includes('interpolation.php')) {
            endpointCalls++;
            assert.ok(url.startsWith(base));
            if (url.endsWith('action=result')) return {status:'current',result:{variable:'bearing_capacity_kpa',method:'IDW fixture',
                legend:{min:145,max:145,unit:'kPa',classes:palette[0]},surface:{type:'FeatureCollection',features:[{
                    ...feature, properties:{...feature.properties,value:145}
                }]}}};
            return {status:'no_data',source_hash:'fixture',eligible_count:0,excluded_count:1,
                outside_borehole_count:1,exclusion_reasons:{outside_study_boundary:1},available_variables:[]};
        }
        if (url.includes('locations.php')) return directory;
        const provinceFeature = { ...feature, geometry: { ...feature.geometry, coordinates: [
            ...feature.geometry.coordinates,
            [[124.82,10.16],[124.84,10.16],[124.84,10.18],[124.82,10.18],[124.82,10.16]]
        ] } };
        return { type: 'FeatureCollection', features: [url.includes('_boundary.') ? provinceFeature : feature] };
    } });
    window.fetch = fetcher;
    const context = vm.createContext({ window, document, L, fetch: fetcher, AbortController, URLSearchParams,
        CustomEvent: class { constructor(type, options = {}) { this.type = type; this.detail = options.detail; } },
        Option: class { constructor(text, value) { this.text = text; this.value = value; } },
        ResizeObserver: class { observe() {} } });
    for (const file of ['interpolation.js', 'distance-measure.js', 'gis.js']) {
        vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js', file), 'utf8'), context);
    }
    const classifier = window.SBCIS_BEARING_CAPACITY;
    for (const [value, color] of [[0, '#E53935'], [99.99, '#E53935'], [100, '#FF7043'],
        [135, '#FF7043'], [150, '#FF7043'], [150.01, '#FFE082'], [200, '#FFE082'],
        [200.01, '#8BCF64'], [250, '#8BCF64'], [250.01, '#1B985A'], ['275', '#1B985A']]) {
        assert.equal(classifier.classify(value)?.color, color, `Capacity boundary ${value}`);
    }
    for (const missing of [null, undefined, '', '   ', 'invalid', NaN, Infinity, false]) {
        assert.equal(classifier.classify(missing), null, 'Missing or invalid capacity stays unknown');
    }
    assert.equal(classifier.boreholeClass({ layers: [] }).color, '#9CA3AF');
    await document.events.DOMContentLoaded();
    assert.equal(map.options.scrollWheelZoom, !embedded, 'Embedded preview preserves non-scrolling map behavior');
    const get = id => document.getElementById(id);
    assert.equal(get('gisStatus').textContent, '1 source borehole record available through area details');
    const estimateRoot = get('gisInterpolation');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(estimateRoot.dataset.state, base === '../../' ? 'no_data' : 'current');

    assert.equal(endpointCalls, base === '../../' ? 2 : 1);
    const fitBeforeSurface = map.fitCount;
    map.events.click({ latlng: { lat: 10.15, lng: 124.85 } });
    assert.ok(map.fitCount > fitBeforeSurface, 'Clicking any colored map area zooms to its municipality');
    assert.equal(get('locationName').textContent, 'Maasin');
    assert.equal(get('gisDataModal').hidden, true);
    const fitBeforeSurfaceBarangay = map.fitCount;
    map.events.click({ latlng: { lat: 10.15, lng: 124.85 } });
    assert.ok(map.fitCount > fitBeforeSurfaceBarangay, 'Second click zooms to its barangay');
    assert.equal(get('locationName').textContent, 'Test Barangay');
    assert.equal(get('gisDataModal').hidden, true);
    const boreholePin = markers.find(marker => marker.options.title === 'Fixture: view soil record');
    assert.ok(boreholePin, 'Borehole point is rendered above the interpolation map');
    assert.ok(boreholePin.popup, 'Borehole point opens a data popup');
    assert.equal(boreholePin.options.fillColor, expectedColor, 'Marker uses the saved popup measurement');
    assert.equal(boreholePin.options.radius, 6);
    assert.equal(boreholePin.options.color, '#ffffff');
    assert.equal(boreholePin.options.weight, 2);
    const popupFields = boreholePin.popup.children[2].children;
    assert.equal(popupFields[3].children[1].textContent, capacity === null ? 'Not recorded' : `${capacity} kPa`);
    assert.equal(popupFields[4].children[1].textContent, expectedClass);
    assert.equal(popupFields[4].children[0].textContent, 'Capacity classification');
    assert.equal(popupFields[5].children[0].textContent, 'Soil type / classification');
    const soils = popupFields[5].children[1];
    if (capacity === null) assert.equal(soils.textContent, 'Not recorded', 'Missing layers have a clear fallback');
    else if (capacity === 275) assert.equal(soils.children[0].textContent, 'OH', 'Recorded soil classification is used when type is absent');
    else {
        assert.equal(soils.children.length, 3, 'Every recorded layer is represented');
        assert.equal(soils.children[0].textContent, 'Layer 3 (0–2 m): Clay (CL)');
        assert.equal(soils.children[1].textContent, 'Layer 7 (2–4 m): Sand (SP)');
        assert.equal(soils.children[2].textContent, 'Layer 9: Not recorded');
    }
    boreholePin.events.mouseover({ originalEvent: { pointerType: 'mouse' } });
    assert.equal(boreholePin.popupOpen, true, 'Hover opens the existing popup without a click');
    assert.equal(boreholePin.autoPanWhenOpened, true, 'Hover preserves Leaflet auto-pan for small previews');
    assert.equal(boreholePin.getPopup().options.autoPan, true, 'Click/tap retains the normal auto-pan option');
    boreholePin.events.mouseout();
    const popupElement = boreholePin.getPopup().getElement();
    popupElement.fire('mouseenter');
    flushHoverTimers();
    assert.equal(boreholePin.popupOpen, true, 'Crossing from marker into popup cancels delayed closing');
    popupElement.fire('mouseleave');
    flushHoverTimers();
    assert.equal(boreholePin.popupOpen, false, 'Popup closes after leaving both marker and popup');
    boreholePin.events.mouseover({ originalEvent: { pointerType: 'touch' } });
    assert.equal(boreholePin.popupOpen, false, 'Touch hover emulation does not interfere with tapping');
    boreholePin.events.click();
    assert.equal(boreholePin.popupOpen, true, 'Click/tap binding still opens the popup');
    boreholePin.closePopup();
    const availabilityBadge = markers.find(marker => /borehole.*available/.test(marker.options.title || ''));
    assert.ok(availabilityBadge, 'Area data-availability badge rendered with an accessible count');
    const badgeGroup = availabilityBadge.parent;

    assert.equal(map.hasLayer(badgeGroup), true, 'Province overview displays the count layer');
    for (let cycle = 0; cycle < 3; cycle++) {
        map.zoom = 11;
        map.events.zoomend();
        assert.equal(badgeGroup.children.size, 1, 'Selected municipality displays its barangay count');
        assert.equal([...badgeGroup.children][0].options.title, 'Test Barangay: 1 borehole available');
        map.zoom = 12;
        map.events.zoomend();
        assert.equal(map.hasLayer(badgeGroup), false, 'Detailed zoom hides badges in favor of individual pins');
        assert.equal(map.hasLayer(boreholePin.parent), true);

        assert.equal(boreholePin.options.fillColor, expectedColor, 'Zoom preserves the bearing-capacity marker color');
        map.zoom = 10;
        map.events.zoomend();
        assert.equal(map.hasLayer(badgeGroup), true, 'Zooming out restores the count layer without a refresh');
        assert.equal(badgeGroup.children.size, 1, 'Repeated zoom cycles do not duplicate counts');
        assert.equal([...badgeGroup.children][0].options.title, 'Maasin: 1 borehole available',
            'Province overview restores municipality counts despite the retained barangay selection');
        assert.match([...badgeGroup.children][0].options.icon.html, /<strong>1<\/strong>/);
        assert.equal(map.hasLayer(boreholePin.parent), false, 'Overview hides individual pins');
    }

    const fitBeforeBadge = map.fitCount;
    availabilityBadge.events.click();
    assert.ok(map.fitCount > fitBeforeBadge, 'Borehole badge zooms to its area');
    assert.equal(get('gisDataModal').hidden, true, 'Borehole badge does not open the records modal');
    assert.equal(get('gisCapacityCard').hidden, false, 'Borehole badge opens the bearing-capacity summary');
    get('gisCapacityRecords').fire('click');
    assert.equal(get('gisDataModal').hidden, false, 'Records modal opens only from View borehole records');
    get('closeDataModal').fire('click');
    const fitBeforeLayer = map.fitCount;
    get('quickResetMap').fire('click');
    map.events.click({ latlng: { lat: 10.15, lng: 124.85 } });
    assert.ok(map.fitCount > fitBeforeLayer, 'Municipality map-area click zooms to the selected area');
    assert.equal(get('gisDataModal').hidden, true, 'Boundary click does not open the records modal');
    assert.equal(get('gisCapacityCard').hidden, false, 'Boundary click opens the bearing-capacity readout');
    assert.equal(get('gisCapacityClass').textContent, 'Low');
    assert.equal(get('gisCapacityValue').textContent, '145 kPa');
    assert.equal(get('gisCapacitySwatch').style.backgroundColor, '#FF7043');
    assert.equal(get('gisSurfaceGradient').children.length, 6, 'Legend includes missing borehole data');
    assert.equal(get('gisSurfaceGradient').children[1].children[0].style.backgroundColor, '#FF7043');
    const fitBeforeBarangay = map.fitCount;
    map.events.click({ latlng: { lat: 10.15, lng: 124.85 } });
    assert.ok(map.fitCount > fitBeforeBarangay, 'Barangay map-area click zooms closer to the selected barangay');
    assert.equal(get('locationName').textContent, 'Test Barangay');
    assert.equal(get('gisDataModal').hidden, true, 'Barangay layer click does not open the records modal');
    get('gisCapacityRecords').fire('click');
    assert.equal(get('gisDataModal').hidden, false, 'Capacity readout retains access to borehole records');
    assert.equal(get('modalBoreholeCount').textContent, '1', 'Area modal still aggregates contained boreholes');
    get('closeDataModal').fire('click');
    get('gisMunicipality').value = 'M'; get('gisMunicipality').fire('change');
    assert.equal(get('locationName').textContent, 'Maasin');
    assert.equal(get('gisBarangay').disabled, false);
    get('gisBarangay').value = 'B'; get('gisBarangay').fire('change');
    assert.equal(get('locationName').textContent, 'Test Barangay');
    get('gisSearch').value = 'Maasin'; get('gisSearch').fire('input');
    assert.ok(get('gisResults').children.length > 1);
    get('gisResults').children[1].fire('click');
    assert.equal(get('locationName').textContent, 'Maasin');
    if (mobile) {
        assert.equal(get('gisExplorer').inert, true);
        get('toggleExplorer').fire('click');
        assert.equal(get('gisExplorer').inert, false, 'Explorer remains reachable on mobile');
        get('closeExplorer').fire('click');
        assert.equal(get('toggleExplorer').focused, true, 'Mobile close returns keyboard focus');
    }
    get('resetMap').fire('click');
    assert.equal(get('locationName').textContent, 'Southern Leyte');
    get('gisMunicipality').value = 'M'; get('gisMunicipality').fire('change');
    get('quickResetMap').fire('click');
    assert.equal(get('locationName').textContent, 'Southern Leyte', 'Visible map reset does not require opening Locations');
    assert.equal(get('gisCapacityCard').hidden, true, 'Reset clears the capacity readout');
    assert.equal(estimateRoot.dataset.state, base === '../../' ? 'no_data' : 'current');
    const measurePanel = get('gisMeasurePanel');
    const measureField = key => measurePanel.querySelector('[data-measure="' + key + '"]');
    get('toggleMeasure').fire('click');
    assert.equal(measurePanel.hidden, false);
    const fitsBeforeMeasurement = map.fitCount;
    map.events.click({ latlng: { lat: 0, lng: 0 } });
    assert.equal(measureField('warning').textContent, 'Distance measurement is only available within Southern Leyte.');
    assert.equal(measureField('distance').textContent, 'Straight-line distance: —');
    map.events.click({ latlng: { lat: 10.17, lng: 124.81 } });
    map.events.click({ latlng: { lat: 10.17, lng: 124.86 } });
    assert.ok(measureField('warning').textContent.includes('crosses outside'), 'Inside endpoints cannot cross a hole');
    assert.equal(measureField('distance').textContent, 'Straight-line distance: —');
    measureField('clear').fire('click');
    map.events.click({ latlng: { lat: 10.12, lng: 124.82 } });
    map.events.click({ latlng: { lat: 0, lng: 0 } });
    assert.equal(measureField('warning').hidden, false, 'Outside second point is rejected');
    map.events.click({ latlng: { lat: 10.121, lng: 124.82 } });
    assert.equal(measureField('distance').textContent, 'Straight-line distance: 111 m');
    assert.equal(map.fitCount, fitsBeforeMeasurement, 'Measurement clicks do not trigger location selection');
    const line = [...layers].find(layer => layer.positions);
    assert.equal(line.options.dashArray, '7 6');
    assert.equal(line.options.pane, 'measurementPane');
    measureField('clear').fire('click');
    assert.equal(line.parent.children.size, 0, 'Clear removes measurement lines');
    map.events.click({ latlng: { lat: 10.1, lng: 124.81 } });
    map.events.click({ latlng: { lat: 10.2, lng: 124.81 } });
    assert.equal(measureField('distance').textContent, 'Straight-line distance: 11.1 km');
    measureField('mode').value = 'site';
    measureField('mode').fire('change');
    map.events.click({ latlng: { lat: 10.149, lng: 124.85 } });
    assert.equal(measureField('distance').textContent, 'Straight-line distance: 111 m');
    assert.ok(measureField('details').textContent.includes(expectedClass));
    assert.ok(measureField('details').textContent.includes(capacity === null ? 'Not recorded' : `${capacity} kPa`));
    const siteMarker = markers.find(marker => marker.options.draggable);
    siteMarker.position = { lat: 0, lng: 0 };
    siteMarker.events.dragend();
    assert.equal(siteMarker.getLatLng().lat, 10.149, 'Invalid drag restores the last valid position');
    assert.equal(measureField('distance').textContent, 'Straight-line distance: 111 m', 'Invalid drag preserves the last valid line');
    siteMarker.position = { lat: 10.17, lng: 124.81 };
    siteMarker.events.dragend();
    assert.equal(siteMarker.getLatLng().lat, 10.149, 'Inside drag that crosses outside also restores the last valid position');
    assert.ok(measureField('warning').textContent.includes('crosses outside'));
    siteMarker.position = { lat: 10.148, lng: 124.85 };
    siteMarker.events.dragend();
    assert.equal(measureField('distance').textContent, 'Straight-line distance: 222 m', 'Dragging a site updates distance');
    window.SBCIS_BOREHOLES.push({ borehole_code: 'Outside', latitude: 0, longitude: 0, layers: [] });
    window.SBCIS_BOREHOLES.push({ borehole_code: 'Crossing', latitude: 10.17, longitude: 124.81, layers: [] });
    window.SBCIS_BOREHOLES.push({ borehole_code: 'Second', latitude: 10.149, longitude: 124.851,
        layers: [{ bearing_capacity_kpa: 275 }] });
    siteMarker.events.dragend();
    assert.equal(measureField('borehole').children.length, 3);
    assert.ok(measureField('borehole').children.every(option => !option.text.startsWith('Outside')), 'Outside boreholes cannot be measured to, including on the admin map');
    const crossingOption = measureField('borehole').children.find(option => option.text.startsWith('Crossing'));
    assert.equal(crossingOption.disabled, true, 'Cross-boundary borehole connections are unavailable');
    assert.ok(crossingOption.text.includes('Connection crosses outside'), 'Rejected connections do not display a measured distance');
    assert.ok(measureField('borehole').children[0].text.startsWith('Second'), 'Nearby choices are sorted by distance');
    measureField('borehole').value = String(measureField('borehole').children.findIndex(option => option.text.startsWith('Crossing')));
    measureField('borehole').fire('change');
    assert.ok(measureField('warning').textContent.includes('crosses outside'), 'Borehole choices cannot draw a crossing line');
    assert.equal(measureField('distance').textContent, 'Straight-line distance: 222 m', 'Rejected borehole preserves the valid measurement');
    measureField('borehole').value = '0';
    measureField('borehole').fire('change');
    assert.equal(measureField('details').textContent, 'Second · 275 kPa · Very High');
    assert.equal(measureField('distance').textContent, 'Straight-line distance: 157 m');
    boreholePin.events.click();
    assert.ok(measureField('details').textContent.startsWith('Fixture'), 'Clicking a different borehole updates the measurement');
    measureField('clear').fire('click');
    assert.equal(siteMarker.parent.children.size, 0, 'Clear removes the temporary house marker');
    measureField('close').fire('click');
    assert.equal(measurePanel.hidden, true);
    assert.equal(get('toggleMeasure').attrs['aria-expanded'], 'false');
    get('gisSearch').value = 'Second'; get('gisSearch').fire('input');
    get('gisResults').children[1].fire('click');
    const secondPin = markers.find(marker => marker.popup?.children[0]?.textContent === 'Second');
    assert.ok(secondPin, 'Search-created markers use the shared popup binding');
    assert.equal(secondPin.popupOpen, true);
    boreholePin.events.mouseover({});
    assert.equal(secondPin.popupOpen, false, 'Opening a new borehole closes the previous one');
    secondPin.events.mouseover({});
    assert.equal(boreholePin.popupOpen, false);
    assert.equal(secondPin.popupOpen, true, 'Search-created markers also support hover');
    secondPin.events.mouseout();
    secondPin.events.mouseover({});
    flushHoverTimers();
    assert.equal(secondPin.popupOpen, true, 'Quick re-entry does not flicker or close the popup');
    secondPin.closePopup();
    get('quickResetMap').fire('click');
    window.SBCIS_BOREHOLES.pop();
    window.SBCIS_BOREHOLES.pop();
    window.SBCIS_BOREHOLES.pop();
}
(async () => {
    const gisSource = fs.readFileSync(path.join(__dirname, '../src/js/gis.js'), 'utf8');
    assert.match(gisSource, /SBCIS_BOREHOLE_ENDPOINT/);
    assert.match(gisSource, /map\.on\('moveend'/);
    assert.match(gisSource, /limit=2000/);
    await testMap(false, '../');
    await testMap(true, '../');
    await testMap(false, '../../');
    await testMap(false, '../', null, '#9CA3AF', 'Unknown');
    await testMap(false, '../../', 275, '#1B985A', 'Very High');
    await testMap(true, '../', 135, '#FF7043', 'Low', true);
    console.log('Map integration checks passed: borehole points, area badges, details modal, search, persistent reset, responsive drawers, admin/public paths.');
})().catch(error => { console.error(error); process.exitCode = 1; });
