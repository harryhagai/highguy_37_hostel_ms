(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function fillUserView(user) {
        document.getElementById('viewUserId').textContent = user.id ?? '-';
        document.getElementById('viewUserUsername').textContent = user.username ?? '-';
        document.getElementById('viewUserEmail').textContent = user.email ?? '-';
        document.getElementById('viewUserPhone').textContent = user.phone ?? '-';
        document.getElementById('viewUserRole').textContent = user.role ?? '-';
        document.getElementById('viewUserCreated').textContent = user.created_at ?? '-';
    }

    function fillUserEdit(user) {
        document.getElementById('editUserId').value = user.id ?? '';
        document.getElementById('editUserUsername').value = user.username ?? '';
        document.getElementById('editUserEmail').value = user.email ?? '';
        document.getElementById('editUserPhone').value = user.phone ?? '';
        document.getElementById('editUserRole').value = user.role ?? 'user';
    }

    document.querySelectorAll('.view-user-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillUserView(parseJSON(this.dataset.user, {}));
        });
    });

    document.querySelectorAll('.edit-user-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillUserEdit(parseJSON(this.dataset.user, {}));
        });
    });

    var config = document.getElementById('manageUsersConfig');
    if (!config) return;

    var openModal = config.dataset.openModal || '';
    var editFormData = parseJSON(config.dataset.editForm, null);

    if (openModal === 'editUserModal' && editFormData) {
        fillUserEdit(editFormData);
    }

    if (openModal) {
        var target = document.getElementById(openModal);
        if (target && window.bootstrap) {
            new bootstrap.Modal(target).show();
        }
    }
})();
