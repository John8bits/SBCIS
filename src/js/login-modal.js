document.addEventListener('DOMContentLoaded', () => {
    const loginModal = document.querySelector('#loginModal');
    const openLogin = document.querySelector('#openLogin');
    const closeLogin = document.querySelector('#closeLogin');
    const loginOverlay = document.querySelector('#loginOverlay');
    const emailInput = document.querySelector('#email');
    const passwordInput = document.querySelector('#password');
    const passwordToggle = document.querySelector('#passwordToggle');
    const forgotPassword = document.querySelector('#forgotPassword');

    if (!loginModal || !openLogin) return;

    const setModalOpen = open => {
        loginModal.classList.toggle('active', open);
        loginModal.setAttribute('aria-hidden', String(!open));
        document.body.classList.toggle('login-modal-open', open);

        if (open) {
            window.setTimeout(() => emailInput?.focus(), 200);
        } else {
            openLogin.focus();
        }
    };

    openLogin.addEventListener('click', event => {
        event.preventDefault();
        setModalOpen(true);
    });

    closeLogin?.addEventListener('click', () => setModalOpen(false));
    loginOverlay?.addEventListener('click', () => setModalOpen(false));

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && loginModal.classList.contains('active')) {
            setModalOpen(false);
        }
    });

    passwordToggle?.addEventListener('click', () => {
        const showPassword = passwordInput.type === 'password';
        passwordInput.type = showPassword ? 'text' : 'password';
        passwordToggle.innerHTML = showPassword
<<<<<<< HEAD
            ? '<i class="fa-regular fa-eye-slash"></i>'
=======
            ? '<i class="fa-regular f   a-eye-slash"></i>'
>>>>>>> a6f6c58f7fe7f063db10b4a873b344df37d5806e
            : '<i class="fa-regular fa-eye"></i>';
        passwordToggle.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
    });

    forgotPassword?.addEventListener('click', event => {
        event.preventDefault();
        window.alert('Please contact the support team to reset your password.');
    });
});
