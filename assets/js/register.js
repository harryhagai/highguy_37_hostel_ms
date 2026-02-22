(function () {
    var form = document.querySelector('.auth-form-wrap form');
    if (!form) return;

    var getById = function (id) {
        return document.getElementById(id);
    };

    var fields = {
        username: {
            input: getById('username'),
            feedback: getById('usernameFeedback'),
            check: getById('usernameCheck'),
            validate: function (value) {
                var normalized = String(value || '').trim().replace(/\s+/g, ' ');
                if (!normalized) {
                    return { valid: false, message: 'Full name is required.' };
                }
                if (!/^[A-Za-z]+(?: [A-Za-z]+){2,}$/.test(normalized)) {
                    return { valid: false, message: 'Enter at least 3 names using letters only, separated by spaces.' };
                }
                return { valid: true, message: '' };
            }
        },
        email: {
            input: getById('email'),
            feedback: getById('emailFeedback'),
            check: getById('emailCheck'),
            validate: function (value) {
                var normalized = String(value || '').trim();
                if (!normalized) {
                    return { valid: false, message: 'Email is required.' };
                }
                var looksLikeEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized);
                if (!looksLikeEmail || !/@gmail\.com$/i.test(normalized)) {
                    return { valid: false, message: 'Email must be a valid @gmail.com address.' };
                }
                return { valid: true, message: '' };
            }
        },
        phone: {
            input: getById('phone'),
            feedback: getById('phoneFeedback'),
            check: getById('phoneCheck'),
            validate: function (value) {
                var normalized = String(value || '').trim().replace(/\s+/g, '');
                if (!normalized) {
                    return { valid: false, message: 'Phone number is required.' };
                }
                if (/^0(?:6|7)\d{8}$/.test(normalized)) {
                    return { valid: true, message: '' };
                }
                if (/^\+255(?:6|7)\d{8}$/.test(normalized)) {
                    return { valid: true, message: '' };
                }
                return { valid: false, message: 'Use 10 digits starting 06/07, or +255 followed by 9 digits.' };
            }
        },
        gender: {
            input: getById('gender'),
            feedback: getById('genderFeedback'),
            check: getById('genderCheck'),
            validate: function (value) {
                var normalized = String(value || '').trim().toLowerCase();
                if (normalized !== 'male' && normalized !== 'female') {
                    return { valid: false, message: 'Please select gender.' };
                }
                return { valid: true, message: '' };
            }
        },
        password: {
            input: getById('password'),
            feedback: getById('passwordFeedback'),
            check: getById('passwordCheck'),
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
        }
    };

    var touched = {};

    var setFieldState = function (field, state, forceShowError) {
        if (!field || !field.input) return true;

        var value = field.input.value;
        var result = state || field.validate(value);
        var hasValue = String(value || '').trim() !== '';

        if (!forceShowError && !hasValue && !touched[field.input.id]) {
            field.input.classList.remove('is-valid', 'is-invalid');
            if (field.check) field.check.classList.add('d-none');
            if (field.feedback) field.feedback.classList.remove('d-block');
            return true;
        }

        if (result.valid) {
            field.input.classList.remove('is-invalid');
            field.input.classList.add('is-valid');
            if (field.check) field.check.classList.remove('d-none');
            if (field.feedback) field.feedback.classList.remove('d-block');
            return true;
        }

        field.input.classList.remove('is-valid');
        field.input.classList.add('is-invalid');
        if (field.check) field.check.classList.add('d-none');
        if (field.feedback) {
            field.feedback.textContent = result.message;
            field.feedback.classList.add('d-block');
        }
        return false;
    };

    Object.keys(fields).forEach(function (name) {
        var field = fields[name];
        if (!field || !field.input) return;

        var onChange = function () {
            touched[field.input.id] = true;
            setFieldState(field, null, false);
        };

        field.input.addEventListener('input', onChange);
        field.input.addEventListener('change', onChange);
        field.input.addEventListener('blur', function () {
            touched[field.input.id] = true;
            setFieldState(field, null, true);
        });
    });

    form.addEventListener('submit', function (event) {
        var allValid = true;
        Object.keys(fields).forEach(function (name) {
            var field = fields[name];
            if (!field || !field.input) return;
            touched[field.input.id] = true;
            if (!setFieldState(field, null, true)) {
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
