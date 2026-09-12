document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toast]').forEach(message => {
        const options = {
            icon: message.dataset.icon || 'info',
            title: message.dataset.title || undefined,
            text: message.textContent.trim()
        };
        if (typeof AppToast !== 'undefined') AppToast.fire(options);
        else if (window.Swal) Swal.fire({ ...options, toast: true, position: 'bottom-end', showConfirmButton: false, timer: 4000 });
        else {
            message.hidden = false;
            message.className = `system-message${options.icon === 'error' ? ' error' : ''}`;
        }
    });

    const confirmAction = async options => {
        if (!window.Swal) return window.confirm(`${options.title}\n\n${options.text}`);
        const result = await Swal.fire({
            toast: false,
            position: 'center',
            icon: options.icon || 'question',
            title: options.title,
            text: options.text,
            showCancelButton: true,
            focusCancel: true,
            confirmButtonText: options.confirmText,
            cancelButtonText: options.cancelText || 'Cancel',
            confirmButtonColor: options.danger ? '#8a3f3f' : '#465e51',
            cancelButtonColor: '#6b747c',
            reverseButtons: true
        });
        return result.isConfirmed;
    };

    document.addEventListener('submit', async event => {
        const form = event.target.closest('.delete-record-form');
        if (!form || form.dataset.confirmed === 'true') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const name = form.dataset.recordName || 'this borehole';
        if (await confirmAction({
            icon: 'warning', title: 'Delete this record?',
            text: `${name} and all of its soil layers will be permanently deleted.`,
            confirmText: 'Delete record', danger: true
        })) {
            form.dataset.confirmed = 'true';
            form.submit();
        }
    }, true);

    document.addEventListener('click', async event => {
        const logout = event.target.closest('#logoutButton');
        if (!logout) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (await confirmAction({
            title: 'Sign out?', text: 'You will be returned to the public site.',
            confirmText: 'Sign out', cancelText: 'Stay'
        })) window.location.href = logout.href;
    }, true);
});
