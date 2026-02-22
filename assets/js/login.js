(function () {
    var form = document.querySelector('.auth-form-wrap form');
    if (!form) return;

    var getById = function (id) {
        return document.getElementById(id);
    };

    var fields = {
        email: {
            input: getById('email'),
            feedback: getById('emailFeedback'),
            validate: function (value) {
                var normalized = String(value || '').trim();
                if (!normalized) {
                    return { valid: false, message: 'Email is required.' };
                }
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized)) {
                    return { valid: false, message: 'Please enter a valid email address.' };
                }
                return { valid: true, message: '' };
            }
        },
        password: {
            input: getById('password'),
            feedback: getById('passwordFeedback'),
            validate: function (value) {
                var normalized = String(value || '');
                if (!normalized) {
                    return { valid: false, message: 'Password is required.' };
                }
                return { valid: true, message: '' };
            }
        }
    };

    var touched = {};

    var setFieldState = function (field, forceShowError) {
        if (!field || !field.input) return true;

        var value = field.input.value;
        var result = field.validate(value);
        var hasValue = String(value || '').trim() !== '';

        if (!forceShowError && !hasValue && !touched[field.input.id]) {
            field.input.classList.remove('is-invalid');
            if (field.feedback) field.feedback.classList.remove('d-block');
            return true;
        }

        if (result.valid) {
            field.input.classList.remove('is-invalid');
            if (field.feedback) field.feedback.classList.remove('d-block');
            return true;
        }

        field.input.classList.add('is-invalid');
        if (field.feedback) {
            field.feedback.textContent = result.message;
            field.feedback.classList.add('d-block');
        }
        return false;
    };

    Object.keys(fields).forEach(function (key) {
        var field = fields[key];
        if (!field || !field.input) return;

        var onChange = function () {
            touched[field.input.id] = true;
            setFieldState(field, false);
        };

        field.input.addEventListener('input', onChange);
        field.input.addEventListener('change', onChange);
        field.input.addEventListener('blur', function () {
            touched[field.input.id] = true;
            setFieldState(field, true);
        });
    });

    form.addEventListener('submit', function (event) {
        var allValid = true;
        Object.keys(fields).forEach(function (key) {
            var field = fields[key];
            if (!field || !field.input) return;
            touched[field.input.id] = true;
            if (!setFieldState(field, true)) {
                allValid = false;
            }
        });

        if (!allValid) {
            event.preventDefault();
            var firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.focus();
        }
    });

    var togglePasswordBtn = getById('togglePassword');
    var togglePasswordIcon = getById('togglePasswordIcon');
    var passwordInput = getById('password');

    if (togglePasswordBtn && togglePasswordIcon && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
            var nextType = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', nextType);

            if (nextType === 'text') {
                togglePasswordIcon.classList.remove('bi-eye');
                togglePasswordIcon.classList.add('bi-eye-slash');
                togglePasswordBtn.setAttribute('aria-label', 'Hide password');
            } else {
                togglePasswordIcon.classList.remove('bi-eye-slash');
                togglePasswordIcon.classList.add('bi-eye');
                togglePasswordBtn.setAttribute('aria-label', 'Show password');
            }
        });
    }
})();
