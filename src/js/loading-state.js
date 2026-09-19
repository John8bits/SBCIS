(() => {
    const show = ({ title = 'Please wait', message = 'Processing your request securely.' } = {}) => {
        let overlay = document.querySelector('[data-sbcis-loading]');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'sbcis-loading-overlay';
            overlay.dataset.sbcisLoading = '';
            overlay.setAttribute('role', 'alertdialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-live', 'assertive');
            overlay.innerHTML = `
                <div class="sbcis-loading-card" aria-busy="true">
                    <span class="sbcis-loading-mark" aria-hidden="true">
                        <i class="fa-solid fa-layer-group"></i>
                    </span>
                    <strong data-sbcis-loading-title></strong>
                    <p data-sbcis-loading-message></p>
                </div>`;
            document.body.append(overlay);
        }

        overlay.querySelector('[data-sbcis-loading-title]').textContent = title;
        overlay.querySelector('[data-sbcis-loading-message]').textContent = message;
        document.body.classList.add('sbcis-is-loading');
        requestAnimationFrame(() => overlay.classList.add('is-visible'));
    };

    const hide = () => {
        document.querySelector('[data-sbcis-loading]')?.remove();
        document.body.classList.remove('sbcis-is-loading');
    };

    window.SBCISLoading = { show, hide };
    window.addEventListener('pageshow', event => {
        if (event.persisted) hide();
    });
})();
