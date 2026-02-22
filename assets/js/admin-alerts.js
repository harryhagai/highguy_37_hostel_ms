(function () {
    function hasSwal() {
        return typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function';
    }

    function normalize(value) {
        return (value || '').toString().toLowerCase().trim();
    }

    function formAction(form) {
        if (!form || !(form instanceof HTMLFormElement)) return '';
        var input = form.querySelector('input[name="action"]');
        return normalize(input ? input.value : '');
    }

    function formBulkAction(form) {
        if (!form || !(form instanceof HTMLFormElement)) return '';
        var input = form.querySelector('[name="bulk_action_type"]');
        return normalize(input ? input.value : '');
    }

    function buildConfirmProfile(message, options) {
        var text = (message || '').toString().trim() || 'Are you sure?';
        var config = options || {};
        var form = config.form || null;
        var action = normalize(config.action || formAction(form));
        var bulkAction = normalize(config.bulkAction || formBulkAction(form));
        var combined = normalize([text, action, bulkAction].join(' '));

        var profile = {
            title: 'Confirm Action',
            icon: 'question',
            iconHtml: '<i class="bi bi-question-circle-fill"></i>',
            iconColor: '#0d6efd',
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#0d6efd',
        };

        if (
            combined.indexOf('delete') !== -1 ||
            combined.indexOf('remove') !== -1 ||
            action.indexOf('delete') !== -1 ||
            bulkAction.indexOf('delete') !== -1
        ) {
            profile.title = 'Delete Confirmation';
            profile.icon = 'warning';
            profile.iconHtml = '<i class="bi bi-trash-fill"></i>';
            profile.iconColor = '#dc3545';
            profile.confirmButtonText = 'Yes, delete';
            profile.confirmButtonColor = '#dc3545';
            return { text: text, profile: profile };
        }

        if (
            combined.indexOf('disable') !== -1 ||
            combined.indexOf('inactive') !== -1 ||
            combined.indexOf('suspend') !== -1 ||
            action.indexOf('disable') !== -1 ||
            action.indexOf('inactive') !== -1 ||
            bulkAction.indexOf('inactive') !== -1
        ) {
            profile.title = 'Disable Confirmation';
            profile.icon = 'warning';
            profile.iconHtml = '<i class="bi bi-slash-circle-fill"></i>';
            profile.iconColor = '#fd7e14';
            profile.confirmButtonText = 'Yes, disable';
            profile.confirmButtonColor = '#fd7e14';
            return { text: text, profile: profile };
        }

        if (
            combined.indexOf('password reset') !== -1 ||
            combined.indexOf('reset password') !== -1 ||
            action.indexOf('reset') !== -1
        ) {
            profile.title = 'Password Reset';
            profile.icon = 'info';
            profile.iconHtml = '<i class="bi bi-key-fill"></i>';
            profile.iconColor = '#0dcaf0';
            profile.confirmButtonText = 'Yes, send';
            profile.confirmButtonColor = '#0dcaf0';
            return { text: text, profile: profile };
        }

        if (action.indexOf('bulk_') === 0 || combined.indexOf('bulk') !== -1) {
            profile.title = 'Apply Bulk Action';
            profile.icon = 'question';
            profile.iconHtml = '<i class="bi bi-lightning-charge-fill"></i>';
            profile.iconColor = '#6f42c1';
            profile.confirmButtonText = 'Yes, apply';
            profile.confirmButtonColor = '#6f42c1';
            return { text: text, profile: profile };
        }

        return { text: text, profile: profile };
    }

    function fallbackConfirm(message) {
        return window.confirm(message || 'Are you sure?');
    }

    function fallbackAlert(message) {
        window.alert(message || 'Action could not be completed.');
    }

    function confirmAction(message, options) {
        var resolved = buildConfirmProfile(message, options || {});
        var text = resolved.text;
        var profile = resolved.profile;

        if (!hasSwal()) {
            return Promise.resolve(fallbackConfirm(text));
        }

        return window.Swal.fire({
            icon: profile.icon,
            iconHtml: profile.iconHtml,
            iconColor: profile.iconColor,
            title: profile.title,
            text: text,
            showCancelButton: true,
            confirmButtonText: profile.confirmButtonText,
            cancelButtonText: profile.cancelButtonText,
            confirmButtonColor: profile.confirmButtonColor,
            customClass: {
                popup: 'admin-swal-popup',
            },
            reverseButtons: true,
            allowOutsideClick: true,
            allowEscapeKey: true,
            buttonsStyling: true,
        }).then(function (result) {
            return !!result.isConfirmed;
        });
    }

    function warn(message, title) {
        var text = (message || '').toString().trim() || 'Please check your input.';
        if (!hasSwal()) {
            fallbackAlert(text);
            return Promise.resolve();
        }

        return window.Swal.fire({
            icon: 'warning',
            iconHtml: '<i class="bi bi-exclamation-triangle-fill"></i>',
            iconColor: '#f59e0b',
            title: title || 'Check Required',
            text: text,
            confirmButtonText: 'OK',
            confirmButtonColor: '#f59e0b',
            customClass: {
                popup: 'admin-swal-popup',
            },
        }).then(function () {});
    }

    window.AdminAlerts = window.AdminAlerts || {};
    window.AdminAlerts.confirmAction = confirmAction;
    window.AdminAlerts.warn = warn;
})();
