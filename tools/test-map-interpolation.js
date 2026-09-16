// Integration harness for the real map scripts. DOM/Leaflet are doubles, not a visual browser test.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
class Element {
    constructor() {
        this.value = ''; this.children = []; this.events = {}; this.attrs = {}; this.dataset = {}; this.style = {};
        const classes = new Set();
        this.classList = { contains: key => classes.has(key), add: key => classes.add(key), remove: key => classes.delete(key),
            toggle: (key, enabled) => { if (enabled) classes.add(key); else classes.delete(key); } };
        this.nodes = {};
    }
    querySelector(key) { return this.nodes[key] ||= new Element(); }
    addEventListener(key, fn) { this.events[key] = fn; }
    removeEventListener(key) { delete this.events[key]; }
    setAttribute(key, value) { this.attrs[key] = value; }
    getAttribute(key) { return this.attrs[key]; }
    replaceChildren(...children) { this.children = children; this.value = children[0]?.value || ''; }
    add(child) { this.children.push(child); }
    append(...children) { this.children.push(...children); }
    focus() { this.focused = true; }
    fire(key) { this.events[key]?.({ target: this }); }
}
async function testMap(mobile, base) {
    const document = new Element(); document.body = new Element();
    document.getElementById = key => document.querySelector('#' + key);
    document.querySelectorAll = () => [];
    document.createElement = () => new Element();
    const window = new Element();
    window.matchMedia = () => ({ matches: mobile, addEventListener() {} });
    window.location = { search: '' }; window.SBCIS_MAP_BASE = base;
    document.getElementById('gisInterpolation').dataset.mode = base === '../../' ? 'admin' : 'public';
    window.SBCIS_BOREHOLES = [{ borehole_code: 'Fixture', latitude: 10.15, longitude:124.85, layers: [] }];
    const layers = new Set(), markers = [], circleMarkers = [], featureLayers = [];
    const makeLayer = () => ({ addTo(parent) { layers.add(this); this.parent = parent; return this; },
        remove() { layers.delete(this); }, events: {}, on(key, fn) { this.events[key] = fn; return this; }, bindTooltip() {}, bindPopup(html) { this.popup = html; },
        setStyle() {}, clearLayers() { return this; }, addData() { return this; }, bringToFront() { return this; },
        getBounds: () => ({ isValid: () => true }) });
    const map = { createPane: () => ({ style: {} }), once() {}, fitBounds() {}, invalidateSize() {} };
    const geoJSON = (data, options = {}) => {
        const group = makeLayer();
        for (const feature of data?.features || []) {
            if (options.onEachFeature) {
                const layer = makeLayer(); options.onEachFeature(feature, layer); featureLayers.push({ feature, layer });
            }
        }
        return group;
    };
    const L = { map: () => map, tileLayer: makeLayer, geoJSON, layerGroup: makeLayer,
        divIcon: options => options,
        marker: (position, options) => { const marker = makeLayer(); marker.position = position; marker.options = options; markers.push(marker); return marker; },
        circleMarker: () => { const marker = makeLayer(); circleMarkers.push(marker); return marker; },
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
            if (url.endsWith('action=result')) return {status:'unavailable',result:null};
            return {status:'no_data',source_hash:'fixture',eligible_count:0,excluded_count:1,
                outside_borehole_count:1,exclusion_reasons:{outside_study_boundary:1},available_variables:[]};
        }
        return url.includes('locations.php') ? directory : {type:'FeatureCollection', features:[feature]};
    } });
    window.fetch = fetcher;
    const context = vm.createContext({ window, document, L, fetch: fetcher, AbortController, URLSearchParams,
        Option: class { constructor(text, value) { this.text = text; this.value = value; } },
        ResizeObserver: class { observe() {} } });
    for (const file of ['interpolation.js', 'gis.js']) {
        vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/js', file), 'utf8'), context);
    }
    await document.events.DOMContentLoaded();
    const get = id => document.getElementById(id);
    assert.equal(get('gisStatus').textContent, '1 source borehole record available through area details');
    const estimateRoot = get('gisInterpolation');
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(estimateRoot.dataset.state, base === '../../' ? 'no_data' : 'unavailable');
    assert.equal(endpointCalls, base === '../../' ? 2 : 1);
    assert.equal(circleMarkers.length, 0, 'Individual borehole dots are not rendered');
    assert.ok(markers.length > 0, 'Area data-availability badge rendered');
    assert.match(markers[0].options.title, /borehole.*available/, 'Availability badge has an accessible count');
    markers[0].events.click();
    assert.equal(get('gisDataModal').hidden, false, 'Availability badge opens area data directly');
    get('closeDataModal').fire('click');
    featureLayers[0].layer.events.click();
    assert.equal(get('gisDataModal').hidden, false, 'Boundary click opens area data modal');
    assert.equal(get('modalBoreholeCount').textContent, '1', 'Area modal aggregates contained boreholes');
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
    assert.equal(estimateRoot.dataset.state, base === '../../' ? 'no_data' : 'unavailable');
}
(async () => {
    await testMap(false, '../');
    await testMap(true, '../');
    await testMap(false, '../../');
    console.log('Map integration checks passed: availability badges without borehole dots, boundary bottom sheet, area aggregation, search, reset, responsive drawers, admin/public paths.');
})().catch(error => { console.error(error); process.exitCode = 1; });
