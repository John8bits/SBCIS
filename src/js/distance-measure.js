// Temporary, client-side measurements. Uses Leaflet's geographic distance in meters.
class SbcisDistanceMeasure {
    constructor(map, { root, toggle, records, onOpen }) {
        this.map = map;
        this.root = root;
        this.toggle = toggle;
        this.records = records;
        this.active = false;
        this.mode = 'points';
        this.points = [];
        this.selected = null;
        this.lineLayer = L.layerGroup().addTo(map);
        this.pointLayer = L.layerGroup().addTo(map);
        Object.assign(map.createPane('measurementPane').style, { zIndex: '440', pointerEvents: 'none' });
        Object.assign(map.createPane('measurementSitePane').style, { zIndex: '610', pointerEvents: 'auto' });
        toggle.disabled = false;
        toggle.addEventListener('click', () => {
            if (this.active) this.close();
            else {
                onOpen();
                this.active = true;
                root.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
                map.getContainer().classList.add('gis-measuring');
                this.element('mode').value = this.mode;
                this.clear();
            }
        });
        this.element('close').addEventListener('click', () => { this.close(); toggle.focus(); });
        this.element('clear').addEventListener('click', () => this.clear());
        this.element('mode').addEventListener('change', event => {
            this.mode = event.target.value === 'site' ? 'site' : 'points';
            this.clear();
        });
        this.element('borehole').addEventListener('change', event => {
            const record = this.nearby?.[Number(event.target.value)];
            if (record) this.selectBorehole(record);
        });
        this.keyHandler = event => { if (event.key === 'Escape' && this.active) this.close(); };
        document.addEventListener('keydown', this.keyHandler);
        map.once('unload', () => document.removeEventListener('keydown', this.keyHandler));
    }

    element(name) { return this.root.querySelector('[data-measure="' + name + '"]'); }

    formatDistance(meters) {
        return meters >= 1000
            ? (meters / 1000).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' km'
            : meters.toLocaleString(undefined, { maximumFractionDigits: 1 }) + ' m';
    }

    clear() {
        this.points = [];
        this.selected = null;
        this.siteMarker = null;
        this.lineLayer.clearLayers();
        this.pointLayer.clearLayers();
        this.element('site-fields').hidden = this.mode !== 'site';
        this.element('coordinates').textContent = '';
        this.element('details').textContent = '';
        this.element('catalogue').textContent = '';
        this.element('distance').textContent = 'Straight-line distance: —';
        this.element('borehole').replaceChildren(new Option('Place a site on the map first', ''));
        this.element('borehole').disabled = true;
        this.element('instruction').textContent = this.mode === 'site'
            ? 'Click the map to place a proposed construction site.' : 'Click the first location on the map.';
    }

    close() {
        this.clear();
        this.active = false;
        this.root.hidden = true;
        this.toggle.setAttribute('aria-expanded', 'false');
        this.map.getContainer().classList.remove('gis-measuring');
    }

    handleMapClick(event) {
        if (!this.active) return false;
        const lat = Number(event.latlng?.lat), lng = Number(event.latlng?.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return true;
        const point = L.latLng(lat, lng);
        if (this.mode === 'site') this.placeSite(point);
        else {
            if (this.points.length === 2) this.clear();
            this.points.push(point);
            L.circleMarker(point, { pane: 'measurementPane', radius: 5, weight: 2,
                color: '#ffffff', fillColor: '#155e75', fillOpacity: 1, interactive: false }).addTo(this.pointLayer);
            if (this.points.length === 2) {
                this.drawLine(this.points[0], this.points[1]);
                this.element('instruction').textContent = 'Measurement complete. Click to start another measurement.';
            } else this.element('instruction').textContent = 'Click the second location on the map.';
        }
        return true;
    }

    handleBoreholeClick(record) {
        if (!this.active) return false;
        if (this.mode === 'site' && this.points.length) {
            this.selected = record;
            this.refreshRecords();
        }
        else this.handleMapClick({ latlng: { lat: record.latitude, lng: record.longitude } });
        return true;
    }

    placeSite(point) {
        this.points = [point];
        this.pointLayer.clearLayers();
        const icon = document.createElement('span');
        icon.className = 'gis-site-symbol';
        icon.textContent = '⌂';
        this.siteMarker = L.marker(point, {
            pane: 'measurementSitePane', draggable: true, keyboard: true,
            title: 'Proposed construction site. Drag to reposition.',
            icon: L.divIcon({ className: 'gis-site-marker', html: icon, iconSize: [30, 32], iconAnchor: [15, 30] })
        }).addTo(this.pointLayer);
        this.siteMarker.on('dragend', () => {
            this.points = [this.siteMarker.getLatLng()];
            this.refreshRecords();
        });
        this.refreshRecords();
        this.element('instruction').textContent = 'Click a borehole or choose one below. Click the map or drag the house to move the site.';
    }

    refreshRecords() {
        if (!this.active || this.mode !== 'site' || !this.points.length) return;
        const site = this.points[0];
        this.element('coordinates').textContent = 'Site: ' + site.lat.toFixed(6) + ', ' + site.lng.toFixed(6);
        const records = this.records().filter(record => record.latitude !== null && record.longitude !== null &&
            Number.isFinite(Number(record.latitude)) && Number.isFinite(Number(record.longitude)));
        if (this.selected && !records.some(record => this.recordKey(record) === this.recordKey(this.selected))) records.push(this.selected);
        this.nearby = records.sort((a, b) => this.distanceTo(a) - this.distanceTo(b));
        const select = this.element('borehole');
        select.replaceChildren();
        this.nearby.forEach((record, index) => select.add(new Option(
            (record.borehole_code || 'Borehole') + ' · ' + this.formatDistance(this.distanceTo(record)), String(index))));
        select.disabled = !this.nearby.length;
        this.element('catalogue').textContent = window.SBCIS_CATALOGUE_TRUNCATED
            ? 'Options use currently loaded map records and the selected borehole; other boreholes may be closer.'
            : 'Boreholes are ordered by straight-line distance from your site.';
        if (this.nearby.length) this.selectBorehole(this.nearby.find(record => this.recordKey(record) === this.recordKey(this.selected)) || this.nearby[0]);
        else {
            this.lineLayer.clearLayers();
            this.element('distance').textContent = 'Straight-line distance: —';
            this.element('details').textContent = 'No borehole records are loaded in this map area.';
        }
    }

    recordKey(record) { return record ? String(record.borehole_id ?? record.borehole_code) : null; }
    distanceTo(record) { return this.map.distance(this.points[0], L.latLng(Number(record.latitude), Number(record.longitude))); }

    selectBorehole(record) {
        this.selected = record;
        const index = this.nearby?.findIndex(item => this.recordKey(item) === this.recordKey(record));
        if (index >= 0) this.element('borehole').value = String(index);
        const capacity = window.SBCIS_BEARING_CAPACITY.boreholeValue(record);
        const band = window.SBCIS_BEARING_CAPACITY.boreholeClass(record);
        const value = window.SBCIS_BEARING_CAPACITY.classify(capacity)
            ? Number(capacity).toLocaleString(undefined, { maximumFractionDigits: 2 }) + ' kPa' : 'Not recorded';
        this.element('details').textContent = (record.borehole_code || 'Borehole') + ' · ' + value + ' · ' + band.label;
        this.drawLine(this.points[0], L.latLng(Number(record.latitude), Number(record.longitude)));
    }

    drawLine(start, end) {
        this.lineLayer.clearLayers();
        const text = 'Straight-line distance: ' + this.formatDistance(this.map.distance(start, end));
        const label = document.createElement('span');
        label.textContent = text;
        L.polyline([start, end], { pane: 'measurementPane', color: '#155e75', weight: 3,
            dashArray: '7 6', interactive: false }).addTo(this.lineLayer)
            .bindTooltip(label, { permanent: true, direction: 'center', className: 'gis-distance-label', interactive: false });
        this.element('distance').textContent = text;
    }
}

window.SbcisDistanceMeasure = SbcisDistanceMeasure;
