(() => {
    let lastActivator = null;
    const rememberActivator = event => {
        const target = event.target instanceof Element ? event.target.closest('button, a, [role="button"], input[type="submit"]') : null;
        if (target) lastActivator = target;
    };
    document.addEventListener('pointerdown', rememberActivator, true);
    document.addEventListener('click', rememberActivator, true);

    const apply = (modal, source) => {
        if (!(modal instanceof Element) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const rect = modal.getBoundingClientRect();
        const trigger = source instanceof Element ? source : lastActivator;
        const sourceRect = trigger?.getBoundingClientRect?.();
        const x = Number.isFinite(source?.clientX) ? source.clientX : sourceRect ? sourceRect.left + sourceRect.width / 2 : null;
        const y = Number.isFinite(source?.clientY) ? source.clientY : sourceRect ? sourceRect.top + sourceRect.height / 2 : null;
        if (!rect.width || !rect.height || x === null || y === null) return;
        modal.style.setProperty('--modal-origin-x', `${Math.round(x - rect.left)}px`);
        modal.style.setProperty('--modal-origin-y', `${Math.round(y - rect.top)}px`);
        modal.classList.remove('sbcis-modal-origin');
        modal.classList.remove('sbcis-modal-origin-complete');
        void modal.offsetWidth;
        modal.classList.add('sbcis-modal-origin');
        modal.addEventListener('animationend', () => {
            modal.classList.remove('sbcis-modal-origin');
            // Keep SweetAlert's built-in show animation from replaying after
            // the origin animation completes.
            modal.classList.add('sbcis-modal-origin-complete');
        }, { once: true });
    };

    window.SBCISModalOrigin = { apply };
    const animatedAlerts = new WeakSet();
    const animateAlertOnce = popup => {
        // SweetAlert builds its container in several DOM insertions. Without
        // this guard, the same popup can restart its entrance animation.
        if (animatedAlerts.has(popup)) return;
        animatedAlerts.add(popup);
        requestAnimationFrame(() => apply(popup));
    };
    new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (!(node instanceof Element)) return;
        const popup = node.matches('.swal2-popup') ? node : node.querySelector('.swal2-popup');
        if (popup) animateAlertOnce(popup);
    }))).observe(document.documentElement, { childList: true, subtree: true });
})();
