// Public/embed pages view only published server results. Admin actions remain authenticated server operations.
class SbcisInterpolation {
    constructor(map, options = {}) {
        this.map = map;
        this.root = options.root || document.querySelector('#gisInterpolation');
        this.base = options.base || '../';
        this.previewResult = options.previewResult || null;
        this.admin = this.root.dataset.mode === 'admin';
        this.preview = this.root.dataset.mode === 'preview';
        // Keep Window as the receiver. Detached native fetch throws in Chrome.
        this.fetcher = options.fetcher || ((url, init) => window.fetch(url, init));
        this.requestId = 0;
        this.listeners = [];
        this.destroyed = false;
        this.busy = false;
        this.pane = map.createPane('interpolationPane');
        Object.assign(this.pane.style, { zIndex: '350', pointerEvents: 'none', opacity: '0.68' });
        this.layer = L.geoJSON(null, {
            pane: 'interpolationPane',
            interactive: false,
            style: feature => ({ color: this.color(feature.properties.value), weight: 0, fillOpacity: 1 })
        }).addTo(map);
        if (this.admin) {
            this.on(this.element('regenerate'), 'click', () => this.refresh(true));
            this.on(this.element('retry'), 'click', () => this.refresh());
        }
        this.on(window, 'focus', () => { if (!this.busy) this.refresh(); });
        map.once('unload', () => this.destroy());
        this.refresh();
    }

    element(name) { return this.root.querySelector('[data-interpolation="' + name + '"]'); }

    on(element, event, handler) {
        element.addEventListener(event, handler);
        this.listeners.push(() => element.removeEventListener(event, handler));
    }

    async request(action, signal) {
        if (this.previewResult && action === 'result') return this.previewResult;
        const regenerate = action === 'regenerate';
        const response = await this.fetcher(this.base + 'app/Controllers/interpolation.php?action=' + action, {
            method: regenerate ? 'POST' : 'GET',
            signal,
            cache: 'no-store',
            credentials: 'same-origin',
            headers: regenerate ? { 'X-CSRF-Token': this.root.dataset.csrf || '' } : {}
        });
        if (!response.headers.get('content-type')?.includes('application/json')) {
            throw new Error('Interpolation endpoint returned non-JSON (HTTP ' + response.status + ').');
        }
        const data = await response.json();
        if (!response.ok) {
            const error = new Error('Interpolation request failed (HTTP ' + response.status + ', ' + data.status + ').');
            error.status = data.status;
            throw error;
        }
        return data;
    }

    async refresh(regenerate = false) {
        if (this.destroyed || (regenerate && (!this.admin || this.busy))) return;
        const id = ++this.requestId;
        this.abort?.abort();
        this.abort = new AbortController();
        this.busy = true;
        this.layer.clearLayers();
        this.element('legend').textContent = 'No approved estimated surface is displayed.';
        this.element('scale').hidden = true;
        this.element('ticks').hidden = true;
        this.show('loading');
        try {
            if (this.admin) {
                const status = await this.request(regenerate ? 'regenerate' : 'status', this.abort.signal);
                if (id !== this.requestId || this.destroyed) return;
                if (!['current', 'needs_regeneration', 'no_data', 'insufficient_data', 'pending_configuration', 'generation_failed'].includes(status.status) ||
                    !Number.isInteger(status.eligible_count) || !Number.isInteger(status.excluded_count)) {
                    throw new Error('Invalid interpolation management response.');
                }
                this.show(status.status, status);
            }
            const data = await this.request('result', this.abort.signal);
            if (id !== this.requestId || this.destroyed) return;
            if (data.status === 'current') {
                const result = data.result;
                if (!result || result.surface?.type !== 'FeatureCollection' || !Array.isArray(result.surface.features) ||
                    !Number.isFinite(result.legend?.min) || !Number.isFinite(result.legend?.max) || result.legend.min > result.legend.max ||
                    typeof result.legend.unit !== 'string') throw new Error('Invalid published interpolation result.');
                this.legend = result.legend;
                window.SBCIS_ACTIVE_INTERPOLATION = result;
                this.layer.addData(result.surface);
                this.element('measurement').textContent = this.variableLabel(result.variable);
                this.element('method').textContent = result.method;
                this.element('legend').textContent = this.number(result.legend.min) + ' to ' + this.number(result.legend.max) + ' ' +
                    result.legend.unit + ' — interpolated numeric values, not engineering suitability classes.';
                this.element('scale').hidden = false;
                this.setTicks(result.legend);
                if (typeof window.dispatchEvent === 'function' && typeof CustomEvent === 'function') {
                    window.dispatchEvent(new CustomEvent('sbcis:interpolation-updated', { detail: result }));
                }
                if (!this.admin) this.show('current', result);
            } else if (!['unavailable', 'outdated'].includes(data.status)) {
                throw new Error('Invalid interpolation result state.');
            } else if (!this.admin) {
                window.SBCIS_ACTIVE_INTERPOLATION = null;
                this.show(data.status);
            }
        } catch (error) {
            if (id !== this.requestId || this.destroyed || error.name === 'AbortError') return;
            console.error('SBCIS interpolation:', error);
            this.layer.clearLayers();
            this.show(error.status === 'unauthorized' || error.status === 'forbidden' ? error.status : 'system_error');
        } finally {
            if (id === this.requestId) {
                this.busy = false;
                if (this.admin) {
                    this.element('regenerate').disabled = false;
                    this.element('retry').disabled = false;
                }
            }
        }
    }

    setTicks(legend) {
        const midpoint = (legend.min + legend.max) / 2;
        const nodes = this.element('ticks').querySelectorAll ? this.element('ticks').querySelectorAll('span') : [];
        [legend.min, midpoint, legend.max].forEach((value, index) => {
            if (nodes[index]) nodes[index].textContent = this.number(value) + (index === 2 ? ' ' + legend.unit : '');
        });
        this.element('ticks').hidden = false;
    }

    color(value) {
        const range = this.legend.max - this.legend.min;
        const fraction = range ? Math.max(0, Math.min(1, (value - this.legend.min) / range)) : 0.5;
        const stops = [
            [44, 123, 182], [0, 166, 202], [0, 204, 188], [144, 235, 157],
            [255, 255, 140], [249, 208, 87], [242, 158, 46], [231, 104, 24], [215, 25, 28]
        ];
        const position = fraction * (stops.length - 1);
        const start = Math.floor(position);
        const end = Math.min(stops.length - 1, start + 1);
        const mix = position - start;
        const rgb = stops[start].map((channel, index) => Math.round(channel + (stops[end][index] - channel) * mix));
        return 'rgb(' + rgb.join(',') + ')';
    }

    number(value) {
        return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    variableLabel(variable) {
        const labels = { bearing_capacity_kpa: 'Bearing capacity', spt_n_value: 'SPT N-value' };
        return labels[variable] || 'Not configured';
    }

    reasonLabel(reason) {
        const labels = {
            non_field_demo_record: 'Demo / non-field records',
            outside_study_boundary: 'Outside Southern Leyte',
            invalid_coordinates: 'Invalid coordinates',
            no_observation: 'No soil observation',
            variable_not_selected: 'Measurement not configured',
            null_measurement: 'Missing measurement',
            invalid_measurement: 'Invalid measurement',
            invalid_depth_interval: 'Invalid depth interval',
            duplicate_coordinates: 'Duplicate coordinates'
        };
        return labels[reason] || reason.replaceAll('_', ' ');
    }

    show(status, data = null) {
        this.root.dataset.state = status;
        this.root.setAttribute('aria-busy', String(status === 'loading'));
        const labels = {
            loading: 'Checking data', current: 'Current approved surface', unavailable: 'Interpolation unavailable',
            outdated: 'Update required', needs_regeneration: 'Update required', no_data: 'No verified source data',
            insufficient_data: 'Insufficient measurements', pending_configuration: 'Configuration required',
            generation_failed: 'Generation unsuccessful', unauthorized: 'Sign-in required', forbidden: 'Page refresh required',
            system_error: 'Service unavailable'
        };
        this.element('state-label').textContent = labels[status] || labels.system_error;
        if (this.preview && status === 'current') this.element('state-label').textContent = 'Synthetic UI preview';
        if (status === 'loading' || status === 'system_error') {
            this.element('scale').hidden = true;
            this.element('ticks').hidden = true;
        }
        if (!this.admin) {
            const countText = typeof document === 'undefined' ? '' : (document.querySelector('#visibleBoreholeCount')?.textContent || '');
            const noVisiblePoints = countText === '0';
            this.element('status').textContent = status === 'current' ? (this.preview ?
                'A synthetic area surface is displayed for interface review only. Click an area to inspect sample records.' :
                'The latest approved estimated surface is displayed.') :
                status === 'loading' ? 'Loading the latest approved interpolation...' :
                noVisiblePoints ? 'No verified borehole measurements within Southern Leyte are currently available to support interpolation.' :
                'No current approved interpolation is available. Measured records remain accessible through area details.';
            return;
        }
        const messages = {
            loading: 'Checking interpolation readiness and publication status...',
            current: 'The approved surface matches the current verified source data.',
            needs_regeneration: 'Verified source data changed after the last publication.',
            no_data: 'No verified field measurements within Southern Leyte are eligible for interpolation.',
            insufficient_data: 'Not enough verified measurement locations are available.',
            pending_configuration: 'An approved measurement, depth policy, method and coverage policy are still required.',
            generation_failed: 'Generation failed. Any previous result has been retained separately.',
            unauthorized: 'Your session has expired. Sign in again.',
            forbidden: 'Reload this admin page before retrying.',
            system_error: 'The interpolation service could not be reached or returned an error. Please retry.'
        };
        this.element('status').textContent = messages[status] || messages.system_error;
        this.element('regenerate').disabled = status === 'loading';
        this.element('retry').disabled = status === 'loading';
        this.element('counts').textContent = data ? data.eligible_count + ' eligible observations; ' + data.excluded_count + ' excluded.' : '';
        this.element('reasons').textContent = data ? Object.entries(data.exclusion_reasons || {})
            .map(([reason, count]) => this.reasonLabel(reason) + ': ' + count).join('; ') : '';
        this.element('measurement').textContent = data ? this.variableLabel(data.variable) : 'Not configured';
        this.element('method').textContent = data?.method || 'Not available';
        this.element('metadata').textContent = data ? 'Measurement: ' + this.variableLabel(data.variable) +
            '. Method: ' + (data.method || 'not approved') + '. Last generated: ' + (data.last_generated_at || 'never') +
            (data.published_outdated ? ' (outdated; hidden from public map)' : '') +
            '. Last checked: ' + (data.last_attempt_at || 'never') + '. Source version: ' + data.source_hash : '';
        this.element('admin').textContent = data ? (data.outside_borehole_count || 0) +
            ' outside-boundary borehole(s); ' + (data.non_field_borehole_count || 0) +
            ' non-field demo borehole(s). Exclusions do not modify source records.' : '';
    }

    destroy() {
        this.destroyed = true;
        this.requestId++;
        this.abort?.abort();
        this.layer.clearLayers();
        this.layer.remove();
        this.listeners.forEach(remove => remove());
        this.listeners = [];
    }
}

if (typeof module !== 'undefined' && module.exports) module.exports = SbcisInterpolation;
else window.SbcisInterpolation = SbcisInterpolation;
