const config = JSON.parse(document.getElementById('record-config').textContent);
const recordDialog = document.getElementById('record-dialog');
const recordForm = document.getElementById('record-form');
const openRecord = document.getElementById('openRecord');
const entries = document.getElementById('layerEntries');
const municipality = document.getElementById('municipality_name');
const barangay = document.getElementById('barangay_name');
let hasDraft = recordDialog.dataset.autoOpen === 'true' && !!document.getElementById('record-error');
function openForm() {
    recordDialog.showModal(); document.body.classList.add('modal-open');
    document.getElementById('borehole_code').focus();
}
openRecord.addEventListener('click', openForm);
recordDialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => recordDialog.close()));
recordDialog.addEventListener('close', () => { document.body.classList.remove('modal-open'); openRecord.focus(); });
if (recordDialog.dataset.autoOpen === 'true') openForm();
function fillBarangays(selected = '') {
    barangay.replaceChildren(new Option(municipality.value ? 'Select barangay (optional)' : 'Select a municipality first', ''));
    barangay.disabled = !municipality.value;
    config.barangays.filter(row => row.municipalityName === municipality.value).forEach(row => barangay.add(new Option(row.name, row.name)));
    barangay.value = selected;
}
municipality.addEventListener('change', () => fillBarangays()); fillBarangays(config.selectedBarangay);
function numberLayers() {
    [...entries.children].forEach((entry, i) => {
        entry.querySelector('legend').textContent = `Layer ${i + 1}`;
        const remove = entry.querySelector('.layer-remove'); remove.disabled = entries.children.length === 1;
        remove.setAttribute('aria-label', `Remove layer ${i + 1}`);
    });
}
document.getElementById('addLayer').addEventListener('click', () => {
    const entry = entries.firstElementChild.cloneNode(true);
    entry.querySelectorAll('input, textarea').forEach(field => { field.value = ''; field.defaultValue = ''; field.setCustomValidity(''); });
    entries.append(entry); numberLayers(); hasDraft = true; entry.querySelector('input').focus();
});
entries.addEventListener('click', async event => {
    const remove = event.target.closest('.layer-remove');
    if (!remove || entries.children.length <= 1) return;
    const entry = remove.closest('.layer-entry');
    if ([...entry.querySelectorAll('input, textarea')].some(field => field.value !== '')) {
        const confirmed = window.Swal
            ? (await Swal.fire({
                toast: false, position: 'center',
                icon: 'question', title: 'Remove this layer?',
                text: 'The layer will be removed from this form. Save the record to apply the change.',
                showCancelButton: true, focusCancel: true, reverseButtons: true,
                confirmButtonText: 'Remove layer', cancelButtonText: 'Keep layer',
                confirmButtonColor: '#8a3f3f', cancelButtonColor: '#6b747c'
            })).isConfirmed
            : window.confirm('Remove this layer from your draft?');
        if (!confirmed) return;
    }
    entry.remove(); numberLayers(); hasDraft = true; validateDepths();
});
function validateDepths() {
    let previousEnd = 0;
    const depth = Number(document.getElementById('borehole_depth_m').value);
    [...entries.children].forEach(entry => {
        const from = entry.querySelector('[name="depth_from_m[]"]');
        const to = entry.querySelector('[name="depth_to_m[]"]');
        from.setCustomValidity(from.value !== '' && Number(from.value) < previousEnd ? 'Layers must be entered in depth order without overlapping.' : '');
        let message = '';
        if (to.value !== '' && from.value !== '' && Number(to.value) <= Number(from.value)) message = 'Ending depth must be greater than starting depth.';
        else if (to.value !== '' && depth > 0 && Number(to.value) > depth) message = 'This layer extends beyond the borehole depth.';
        to.setCustomValidity(message); previousEnd = to.value === '' ? previousEnd : Number(to.value);
    });
}
recordForm.addEventListener('input', () => { hasDraft = true; validateDepths(); });
recordForm.addEventListener('change', () => { hasDraft = true; });
recordForm.addEventListener('submit', event => {
    validateDepths();
    if (!recordForm.reportValidity()) { event.preventDefault(); return; }
    hasDraft = false;
    const save = recordForm.querySelector('[type="submit"]'); save.disabled = true; save.textContent = 'Saving…';
});
window.addEventListener('beforeunload', event => { if (hasDraft) { event.preventDefault(); event.returnValue = ''; } });
numberLayers(); validateDepths();
const menuButton = document.getElementById('mobileMenu');
const navSidebar = document.getElementById('sidebar');
menuButton?.addEventListener('click', () => { menuButton.setAttribute('aria-expanded', String(navSidebar.classList.toggle('open'))); });
