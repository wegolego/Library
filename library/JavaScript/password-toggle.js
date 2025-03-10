document.addEventListener('DOMContentLoaded', function() {
    const passwordInputs = document.querySelectorAll('.password-wrapper');

    passwordInputs.forEach(passwordWrapper => {
        const passwordInput = passwordWrapper.querySelector('input[type="password"]');
        const togglePasswordIcon = passwordWrapper.querySelector('.toggle-password');

        if (passwordInput && togglePasswordIcon) {
            togglePasswordIcon.addEventListener('click', function() {
                // Toggle the type attribute
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Toggle the icon
                if (type === 'password') {
                    togglePasswordIcon.classList.remove('fa-eye-slash');
                    togglePasswordIcon.classList.add('fa-eye');
                } else {
                    togglePasswordIcon.classList.remove('fa-eye');
                    togglePasswordIcon.classList.add('fa-eye-slash');
                }
            });
        }
    });
});
