document.addEventListener('DOMContentLoaded', () => {
    const frame = document.getElementById('heroMap');
    if (!frame) return;

    const reset = document.getElementById('heroMapReset');
    const status = document.getElementById('heroMapStatus');
    const legend = document.getElementById('heroInterpolationLegend');
    let observer;
    let mapReset;

    // The same-origin preview runs the public GIS runtime. This bridge only
    // connects the homepage's existing controls and status to that instance.
    function connect() {
        observer?.disconnect();
        const preview = frame.contentDocument;
        mapReset = preview?.getElementById('quickResetMap');
        const mapStatus = preview?.getElementById('gisStatus');
        if (!mapReset || !mapStatus) {
            reset.disabled = true;
            status.textContent = 'Map unavailable. Open the full map to try again.';
            return;
        }
        const sync = () => {
            reset.disabled = mapReset.disabled;
            status.textContent = mapStatus.textContent;
            legend.hidden = mapReset.disabled;
        };
        observer = new MutationObserver(sync);
        observer.observe(mapReset, { attributes: true, attributeFilter: ['disabled'] });
        observer.observe(mapStatus, { childList: true, characterData: true, subtree: true });
        sync();
    }
    reset.addEventListener('click', () => {
        if (mapReset && !mapReset.disabled) mapReset.click();
    });
    frame.addEventListener('load', connect);
    if (frame.contentDocument?.readyState === 'complete') connect();
});
