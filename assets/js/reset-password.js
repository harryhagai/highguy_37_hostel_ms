(function () {
    var form = document.querySelector('.auth-form-wrap form');
    if (!form) return;

    var getById = function (id) {
        return document.getElementById(id);
    };

    var passwordInput = getById('password');
    var passwordConfirmInput = getById('password_confirm');

    var fields = {
        password: {
            input: passwordInput,
            feedback: getById('passwordFeedback'),
            validate: function (value) {
                var normalized = String(value || '');
                if (!normalized) {
                    return { valid: false, message: 'Password is required.' };
                }
                if (!/^(?=.*[A-Za-z])(?=.*\d).{6,}$/.test(normalized)) {
                    return { valid: false, message: 'Password must be at least 6 characters and include letters and numbers.' };
                }
                return { valid: true, message: '' };
            }
        },
        password_confirm: {
            input: passwordConfirmInput,
            feedback: getById('passwordConfirmFeedback'),
            validate: function (value) {
                var normalized = String(value || '');
                if (!normalized) {
                    return { valid: false, message: 'Please confirm your new password.' };
                }
                if (passwordInput && normalized !== String(passwordInput.value || '')) {
                    return { valid: false, message: 'Passwords do not match.' };
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
            if (field.input.id === 'password' && fields.password_confirm && touched.password_confirm) {
                setFieldState(fields.password_confirm, true);
            }
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

    var attachToggle = function (buttonId, iconId, inputId, showLabel, hideLabel) {
        var button = getById(buttonId);
        var icon = getById(iconId);
        var input = getById(inputId);
        if (!button || !icon || !input) return;

        button.addEventListener('click', function () {
            var nextType = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', nextType);

            if (nextType === 'text') {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
                button.setAttribute('aria-label', hideLabel);
            } else {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
                button.setAttribute('aria-label', showLabel);
            }
        });
    };

    attachToggle('togglePassword', 'togglePasswordIcon', 'password', 'Show password', 'Hide password');
    attachToggle('togglePasswordConfirm', 'togglePasswordConfirmIcon', 'password_confirm', 'Show password confirmation', 'Hide password confirmation');
})();
