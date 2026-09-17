const assert = require('node:assert/strict');
class Element {
    constructor() { this.dataset = {}; this.listeners = {}; this.style = {}; this.nodes = {}; }
    addEventListener(name, fn) { this.listeners[name] = fn; }
    removeEventListener(name) { delete this.listeners[name]; }
    setAttribute(name, value) { this[name] = value; }
    querySelector(key) { return this.nodes[key] ||= new Element(); }
}
global.window = new Element();
const requests = [];
window.fetch = function (url, options) {
    assert.equal(this, window, 'Native fetch receiver regression');
    return new Promise(resolve => requests.push({url, options, resolve}));
};
const markers = {};
const layers = new Set([markers]);
global.L = { geoJSON: () => ({ clearLayers() { this.data = null; }, addData(data) { this.data = data; },
    addTo() { layers.add(this); return this; }, remove() { layers.delete(this); } }) };
const Interpolation = require('../src/js/interpolation.js');
const map = { createPane: () => ({style:{}}), once() {} };
const tick = () => new Promise(resolve => setImmediate(resolve));
function response(request, payload, code = 200, contentType = 'application/json') {
    request.resolve({ok:code === 200, status:code, headers:{get:()=>contentType}, json:async()=>payload});
}
const published = {status:'current',result:{method:'isolated fixture',legend:{min:0,max:10,unit:'test units'},
    surface:{type:'FeatureCollection',features:[]}}};
const absent = {status:'unavailable',result:null};
const adminStatus = status => ({status, eligible_count:0,excluded_count:2,exclusion_reasons:{outside_study_boundary:2},
    outside_borehole_count:2,source_hash:'fixture',variable:null,method:null,last_generated_at:null});
(async () => {
    const root = new Element(); root.dataset.mode = 'public';
    const viewer = new Interpolation(map,{root});
    const old = requests.shift();
    assert.ok(old.url.endsWith('action=result'), 'Public automatically loads result');
    viewer.refresh();
    const current = requests.shift();
    response(current, absent); await tick();
    response(old, published); await tick();
    assert.equal(root.dataset.state,'unavailable', 'Stale response ignored');
    assert.equal(viewer.layer.data,null);
    assert.equal(old.options.signal.aborted,true);
    await viewer.refresh(true);
    assert.equal(requests.length,0,'Public cannot initiate generation');
    viewer.refresh(); response(requests.shift(),published); await tick();
    assert.equal(root.dataset.state,'current');
    assert.equal(viewer.layer.data.type,'FeatureCollection');
    assert.ok(viewer.element('legend').textContent.includes('Surface range: 0–10 test units'));
    assert.equal(Interpolation.bearingClass(99).key, 'very-low');
    assert.equal(Interpolation.bearingClass(100).key, 'low');
    assert.equal(Interpolation.bearingClass(151).key, 'moderate');
    assert.equal(Interpolation.bearingClass(201).key, 'high');
    assert.equal(Interpolation.bearingClass(251).key, 'very-high');
    viewer.refresh(); response(requests.shift(),{status:'outdated',result:null}); await tick();
    assert.equal(viewer.layer.data,null,'Outdated surface removed');
    const errors = []; const logger = console.error;
    console.error = (...args) => errors.push(args);
    viewer.refresh(); response(requests.shift(),{},500,'text/html'); await tick();
    assert.equal(root.dataset.state,'system_error');
    assert.equal(viewer.element('status').textContent,'No current interpolation is available. Borehole records remain accessible on the map.');
    assert.equal(errors.length,1,'Technical failure logged');
    viewer.refresh(); const pending = requests.shift(); viewer.destroy(); response(pending,published); await tick();
    assert.equal(layers.has(markers),true);
    assert.equal(layers.size,1,'Only estimate layer removed');
    assert.equal(viewer.pane.style.zIndex,'350');
    assert.equal(viewer.pane.style.pointerEvents,'none');

    const adminRoot = new Element(); adminRoot.dataset = {mode:'admin',csrf:'isolated-token'};
    const admin = new Interpolation(map,{root:adminRoot,base:'../../'});
    assert.ok(requests[0].url.endsWith('action=status'));
    response(requests.shift(),adminStatus('no_data')); await tick();
    response(requests.shift(),absent); await tick();
    assert.equal(adminRoot.dataset.state,'no_data');
    assert.ok(admin.element('admin').textContent.includes('2 borehole(s) are outside the province boundary'));
    admin.element('regenerate').listeners.click();
    const regeneration = requests.shift();
    assert.equal(regeneration.options.method,'POST');
    assert.equal(regeneration.options.headers['X-CSRF-Token'],'isolated-token');
    await admin.refresh(true);
    assert.equal(requests.length,0,'Duplicate regeneration prevented while busy');
    response(regeneration,adminStatus('generation_failed')); await tick();
    response(requests.shift(),absent); await tick();
    assert.equal(adminRoot.dataset.state,'generation_failed');
    admin.element('retry').listeners.click();
    response(requests.shift(),adminStatus('insufficient_data')); await tick();
    response(requests.shift(),absent); await tick();
    assert.equal(adminRoot.dataset.state,'insufficient_data');
    admin.refresh(); response(requests.shift(),{status:'unauthorized'},401); await tick();
    assert.equal(adminRoot.dataset.state,'unauthorized');
    admin.destroy(); console.error = logger;
    console.log('JavaScript checks passed: non-blocking surface, native receiver, public loading, stale responses, numeric legend, admin POST/CSRF, no-data/insufficient/failure/retry.');
})().catch(error => { console.error(error); process.exitCode = 1; });
