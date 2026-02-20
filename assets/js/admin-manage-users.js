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

    function normalizeValue(value) {
        return (value || '').toString().toLowerCase().trim();
    }

    function updateResultCount(target, count) {
        if (!target) return;
        target.textContent = count + (count === 1 ? ' result' : ' results');
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

    var rows = Array.from(document.querySelectorAll('.user-row'));
    var searchInput = document.getElementById('usersSearchInput');
    var roleFilter = document.getElementById('usersRoleFilter');
    var clearSearch = document.getElementById('clearUsersSearch');
    var noResultsRow = document.getElementById('usersNoResultsRow');
    var resultsCount = document.getElementById('usersResultCount');

    function applyUsersFilter() {
        if (!rows.length) {
            updateResultCount(resultsCount, 0);
            if (noResultsRow) noResultsRow.classList.remove('d-none');
            return;
        }

        var term = normalizeValue(searchInput ? searchInput.value : '');
        var role = normalizeValue(roleFilter ? roleFilter.value : '');
        var visibleRows = 0;

        rows.forEach(function (row) {
            var rowSearch = normalizeValue(row.dataset.search);
            var rowRole = normalizeValue(row.dataset.role);
            var matchesSearch = !term || rowSearch.indexOf(term) !== -1;
            var matchesRole = !role || rowRole === role;
            var shouldShow = matchesSearch && matchesRole;
            row.classList.toggle('d-none', !shouldShow);
            if (shouldShow) visibleRows += 1;
        });

        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', visibleRows !== 0);
        }
        updateResultCount(resultsCount, visibleRows);
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyUsersFilter);
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', applyUsersFilter);
    }
    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (roleFilter) roleFilter.value = '';
            applyUsersFilter();
            if (searchInput) searchInput.focus();
        });
    }
    applyUsersFilter();

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
