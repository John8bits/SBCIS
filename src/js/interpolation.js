// Public/embed pages view only published server results. Admin actions remain authenticated server operations.
class SbcisInterpolation {
    constructor(map, options = {}) {
        this.map = map;
        this.root = options.root || document.querySelector('#gisInterpolation');
        this.base = options.base || '../';
        this.admin = this.root.dataset.mode === 'admin';
        // Keep Window as the receiver. Detached native fetch throws in Chrome.
        this.fetcher = options.fetcher || ((url, init) => window.fetch(url, init));
        this.requestId = 0;
        this.listeners = [];
        this.destroyed = false;
        this.busy = false;
        this.shadowPane = map.createPane('interpolationShadowPane');
        this.pane = map.createPane('interpolationPane');
        Object.assign(this.shadowPane.style, {
            zIndex: '340', pointerEvents: 'none', opacity: '0.42',
            transform: 'translate3d(2px, 3px, 0)'
        });
        Object.assign(this.pane.style, {
            zIndex: '350', pointerEvents: 'none', opacity: '0.82',
            filter: 'drop-shadow(0 1px 1px rgba(19, 61, 47, .12))'
        });
        this.shadowLayer = L.geoJSON(null, {
            pane: 'interpolationShadowPane',
            interactive: false,
            style: { color: '#173f2d', weight: 1.8, opacity: .5, fillColor: '#173f2d', fillOpacity: .16 }
        }).addTo(map);
        this.layer = L.geoJSON(null, {
            pane: 'interpolationPane',
            interactive: false,
            style: feature => ({
                color: this.surfaceColor(feature.properties.value),
                // Low-contrast joins keep the categorical surface readable
                // without presenting barangay estimates as contour lines.
                weight: 0.15,
                opacity: 0.12,
                fillColor: this.surfaceColor(feature.properties.value),
                fillOpacity: 0.68
            })
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
        this.shadowLayer.clearLayers();
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
                this.colorScale = this.createColorScale(result.legend);
                window.SBCIS_ACTIVE_INTERPOLATION = result;
                this.shadowLayer.addData(result.surface);
                this.layer.addData(result.surface);
                this.element('measurement').textContent = this.variableLabel(result.variable);
                this.element('method').textContent = result.method;
                const generated = result.generated_at ? new Date(result.generated_at) : null;
                this.element('generated').textContent = generated && !Number.isNaN(generated.valueOf())
                    ? 'Updated ' + generated.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
                    : 'Current published model';
                const visibleCount = typeof document === 'undefined'
                    ? '—'
                    : (document.querySelector('#visibleBoreholeCount')?.textContent || '—');
                const features = result.surface.features;
                const inputCount = Number(features[0]?.properties?.observation_count);
                this.element('observations').textContent = Number.isInteger(inputCount)
                    ? inputCount + ' valid'
                    : visibleCount + ' recorded';
                this.element('coverage').textContent = features.length + ' barangay' + (features.length === 1 ? '' : 's');
                this.element('model').textContent = result.method?.startsWith('IDW')
                    ? 'IDW estimate'
                    : (result.method || 'Published');
                this.element('output-range').textContent = this.number(result.legend.min) + '–' + this.number(result.legend.max) + ' ' + result.legend.unit;
                this.element('legend').textContent = 'Surface range: ' + this.number(result.legend.min) + '–' +
                    this.number(result.legend.max) + ' ' + result.legend.unit + '.';
                this.element('scale').hidden = false;
                this.setTicks(result.legend);
                this.updateFloatingLegend(result.legend);
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
        const ticks = this.element('ticks');
        ticks.textContent = this.number(legend.min) + ' ' + legend.unit + ' — ' +
            this.number(legend.max) + ' ' + legend.unit;
        ticks.hidden = false;
    }

    updateFloatingLegend(legend) {
        if (typeof document === 'undefined') return;
        const card = document.querySelector('#gisSurfaceLegend');
        const min = document.querySelector('#gisSurfaceLegendMin');
        const max = document.querySelector('#gisSurfaceLegendMax');
        const unit = document.querySelector('#gisSurfaceLegendUnit');
        const gradient = document.querySelector('#gisSurfaceGradient');
        if (!card || !min || !max || !unit || !gradient) return;
        min.textContent = this.number(legend.min);
        max.textContent = this.number(legend.max) + ' ' + legend.unit;
        unit.textContent = 'Published range · ' + legend.unit;
        gradient.style.background = 'linear-gradient(90deg, #a94442, #c77745, #d5ad56, #7fa36d, #28745d)';
        card.hidden = false;
    }

    setVisible(visible) {
        this.pane.style.display = visible ? '' : 'none';
        this.shadowPane.style.display = visible ? '' : 'none';
    }

    createColorScale(legend) {
        const d3 = typeof window === 'undefined' ? null : window.d3;
        const min = Number(legend?.min);
        const max = Number(legend?.max);
        // A muted, ordered engineering ramp keeps the surface legible over
        // labels and coastlines. Numeric values and classification limits stay
        // server-defined and unchanged.
        if (d3?.scaleLinear &&
            Number.isFinite(min) && Number.isFinite(max) && max > min) {
            return d3.scaleLinear()
                .domain([min, min + (max - min) * .25, min + (max - min) * .5, min + (max - min) * .75, max])
                .range(['#a94442', '#c77745', '#d5ad56', '#7fa36d', '#28745d'])
                .interpolate(d3.interpolateRgb);
        }
        return null;
    }

    surfaceColor(value) {
        const numeric = Number(value);
        if (this.colorScale && Number.isFinite(numeric)) return this.colorScale(numeric);
        return Number.isFinite(numeric) ? '#28745d' : '#d9e2dd';
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
            outside_study_boundary: 'Outside Southern Leyte',
            invalid_coordinates: 'Invalid coordinates',
            no_observation: 'No soil observation',
            variable_not_selected: 'Measurement not configured',
            null_measurement: 'Missing measurement',
            invalid_measurement: 'Invalid measurement',
            invalid_depth_interval: 'Invalid depth interval',
            non_field_record: 'Unverified record provenance',
            duplicate_coordinates: 'Duplicate coordinates'
        };
        return labels[reason] || reason.replaceAll('_', ' ');
    }

    show(status, data = null) {
        this.root.dataset.state = status;
        this.root.setAttribute('aria-busy', String(status === 'loading'));
        const labels = {
            loading: 'Checking data', current: 'Current interpolation surface', unavailable: 'Interpolation unavailable',
            outdated: 'Update required', needs_regeneration: 'Update required', no_data: 'No verified source data',
            insufficient_data: 'Insufficient measurements', pending_configuration: 'Configuration required',
            generation_failed: 'Generation unsuccessful', unauthorized: 'Sign-in required', forbidden: 'Page refresh required',
            system_error: 'Service unavailable'
        };
        this.element('state-label').textContent = labels[status] || labels.system_error;
        if (status === 'loading' || status === 'system_error') {
            this.element('scale').hidden = true;
            this.element('ticks').hidden = true;
        }
        if (!this.admin) {
            const countText = typeof document === 'undefined' ? '' : (document.querySelector('#visibleBoreholeCount')?.textContent || '');
            const noVisiblePoints = countText === '0';
            this.element('status').textContent = status === 'current' ?
                'The latest interpolation is displayed.' :
                status === 'loading' ? 'Loading the latest approved interpolation...' :
                noVisiblePoints ? 'No borehole measurements are available for interpolation.' :
                'No current interpolation is available. Borehole records remain accessible on the map.';
            return;
        }
        const messages = {
            loading: 'Checking interpolation readiness and publication status...',
            current: 'The surface matches the current source records.',
            needs_regeneration: 'Source records changed. Update the surface.',
            no_data: 'No valid records are available for interpolation.',
            insufficient_data: 'Not enough valid measurement locations are available.',
            pending_configuration: 'An approved measurement, depth policy, method and coverage policy are still required.',
            generation_failed: 'Generation failed. Any previous result has been retained separately.',
            unauthorized: 'Your session has expired. Sign in again.',
            forbidden: 'Reload this admin page before retrying.',
            system_error: 'The interpolation service could not be reached or returned an error. Please retry.'
        };
        this.element('status').textContent = messages[status] || messages.system_error;
        this.element('regenerate').disabled = status === 'loading';
        this.element('retry').disabled = status === 'loading';
        this.element('counts').textContent = data ? data.eligible_count + ' usable observation' + (data.eligible_count === 1 ? '' : 's') +
            (data.excluded_count ? '; ' + data.excluded_count + ' excluded' : '') + '.' : '';
        this.element('reasons').textContent = data ? Object.entries(data.exclusion_reasons || {})
            .map(([reason, count]) => this.reasonLabel(reason) + ': ' + count).join('; ') : '';
        this.element('measurement').textContent = data ? this.variableLabel(data.variable) : 'Not configured';
        this.element('method').textContent = data?.method || 'Not available';
        this.element('metadata').textContent = data ? 'Measurement: ' + this.variableLabel(data.variable) +
            '. Method: ' + (data.method || 'not approved') + '. Last generated: ' + (data.last_generated_at || 'never') +
            (data.published_outdated ? ' (outdated; hidden from public map)' : '') +
            '. Last checked: ' + (data.last_attempt_at || 'never') + '. Source version: ' + data.source_hash : '';
        this.element('admin').textContent = data?.outside_borehole_count ?
            data.outside_borehole_count + ' borehole(s) are outside the province boundary.' : '';
        const validation = data?.validation;
        this.element('validation').textContent = validation ?
            'Leave-one-out validation (' + validation.sample_count +
            (validation.sampled ? ' of ' + validation.population_count : '') + ' points): MAE ' + this.number(validation.mae) +
            ' kPa; RMSE ' + this.number(validation.rmse) + ' kPa; bias ' + this.number(validation.bias) +
            ' kPa. Exact-location maximum error: ' + this.number(validation.exact_location_max_error) + ' kPa.' :
            'Validation metrics will be available after a valid surface is generated.';
    }

    destroy() {
        this.destroyed = true;
        this.requestId++;
        this.abort?.abort();
        this.shadowLayer.clearLayers();
        this.shadowLayer.remove();
        this.layer.clearLayers();
        this.layer.remove();
        this.listeners.forEach(remove => remove());
        this.listeners = [];
    }
}

if (typeof module !== 'undefined' && module.exports) module.exports = SbcisInterpolation;
else window.SbcisInterpolation = SbcisInterpolation;
