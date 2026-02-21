(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function normalizeValue(value) {
        return (value || '').toString().toLowerCase().trim();
    }

    function escapeHtml(value) {
        return (value || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle-tooltip="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    }

    function fillUserView(user) {
        var setText = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value || '-';
        };

        setText('viewUserUsername', user.username);
        setText('viewUserEmail', user.email);
        setText('viewUserPhone', user.phone || '-');
        setText('viewUserRole', user.role);
        setText('viewUserStatus', user.status || '-');
        setText('viewUserLastLogin', user.last_login_display || '-');
        setText('viewUserCreated', user.created_at_display || '-');

        var list = document.getElementById('viewUserActivityList');
        if (!list) return;

        var items = Array.isArray(user.recent_activity) ? user.recent_activity : [];
        if (!items.length) {
            list.innerHTML = '<li class="text-muted">No activity yet.</li>';
            return;
        }

        list.innerHTML = items.map(function (item) {
            var details = item.details ? ' (' + escapeHtml(item.details) + ')' : '';
            return '<li><strong>' + escapeHtml(item.action) + '</strong> - ' + escapeHtml(item.created_at) + details + '</li>';
        }).join('');
    }

    function fillUserEdit(user) {
        var setValue = function (id, value, fallback) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = value || fallback || '';
        };

        setValue('editUserId', user.id, '');
        setValue('editUserUsername', user.username, '');
        setValue('editUserEmail', user.email, '');
        setValue('editUserPhone', user.phone, '');
        setValue('editUserRole', user.role, 'user');
        setValue('editUserStatus', user.status, 'active');
    }

    function updateResultCount(target, count) {
        if (!target) return;
        target.textContent = count + (count === 1 ? ' result' : ' results');
    }

    var rows = Array.from(document.querySelectorAll('.user-row'));
    var searchInput = document.getElementById('usersSearchInput');
    var roleFilter = document.getElementById('usersRoleFilter');
    var statusFilter = document.getElementById('usersStatusFilter');
    var dateFrom = document.getElementById('usersDateFrom');
    var dateTo = document.getElementById('usersDateTo');
    var clearSearch = document.getElementById('clearUsersSearch');
    var noResultsRow = document.getElementById('usersNoResultsRow');
    var resultsCount = document.getElementById('usersResultCount');
    var loadedInfo = document.getElementById('usersLoadedInfo');
    var selectAllUsers = document.getElementById('selectAllUsers');
    var bulkSelectedCount = document.getElementById('bulkSelectedCount');
    var bulkForm = document.getElementById('bulkUsersForm');
    var bulkSelectedInputs = document.getElementById('bulkSelectedInputs');
    var bulkActionType = document.getElementById('bulkActionType');
    var lazySentinel = document.getElementById('usersLazySentinel');
    var tableWrap = document.querySelector('.users-table-wrap');

    var state = {
        batchSize: 20,
        visibleLimit: 20,
        filteredRows: rows.slice(),
    };

    var loadingMore = false;

    function compareDateRange(created, fromValue, toValue) {
        if (!created) return !fromValue && !toValue;
        if (fromValue && created < fromValue) return false;
        if (toValue && created > toValue) return false;
        return true;
    }

    function getVisibleRows() {
        return state.filteredRows.slice(0, state.visibleLimit);
    }

    function updateLoadedInfo(total, visible) {
        if (!loadedInfo) return;
        if (!total) {
            loadedInfo.textContent = 'Showing 0 of 0';
            return;
        }

        if (visible >= total) {
            loadedInfo.textContent = 'Showing ' + total + ' of ' + total;
            return;
        }

        loadedInfo.textContent = 'Showing ' + visible + ' of ' + total;
    }

    function updateBulkCount() {
        if (!bulkSelectedCount) return;
        var selected = rows.filter(function (row) {
            var cb = row.querySelector('.user-select');
            return cb && cb.checked;
        }).length;
        bulkSelectedCount.textContent = selected + ' selected';
    }

    function syncSelectAllControl(visibleRows) {
        if (!selectAllUsers) return;

        if (!visibleRows.length) {
            selectAllUsers.checked = false;
            selectAllUsers.indeterminate = false;
            return;
        }

        var checkedCount = visibleRows.filter(function (row) {
            var cb = row.querySelector('.user-select');
            return cb && cb.checked;
        }).length;

        selectAllUsers.checked = checkedCount === visibleRows.length;
        selectAllUsers.indeterminate = checkedCount > 0 && checkedCount < visibleRows.length;
    }

    function renderUsersTable() {
        rows.forEach(function (row) {
            row.classList.add('d-none');
        });

        var visibleRows = getVisibleRows();
        visibleRows.forEach(function (row) {
            row.classList.remove('d-none');
        });

        var total = state.filteredRows.length;
        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', total !== 0);
        }

        updateResultCount(resultsCount, total);
        updateLoadedInfo(total, visibleRows.length);
        syncSelectAllControl(visibleRows);
        updateBulkCount();
    }

    function loadMoreUsers() {
        if (loadingMore) return;
        if (state.visibleLimit >= state.filteredRows.length) return;

        loadingMore = true;
        state.visibleLimit = Math.min(state.visibleLimit + state.batchSize, state.filteredRows.length);
        renderUsersTable();

        window.requestAnimationFrame(function () {
            loadingMore = false;
        });
    }

    function applyUsersFilter(resetVisible) {
        if (resetVisible) {
            state.visibleLimit = state.batchSize;
        }

        var term = normalizeValue(searchInput ? searchInput.value : '');
        var role = normalizeValue(roleFilter ? roleFilter.value : '');
        var status = normalizeValue(statusFilter ? statusFilter.value : '');
        var fromDate = (dateFrom ? dateFrom.value : '').trim();
        var toDate = (dateTo ? dateTo.value : '').trim();

        state.filteredRows = rows.filter(function (row) {
            var rowSearch = normalizeValue(row.dataset.search);
            var rowRole = normalizeValue(row.dataset.role);
            var rowStatus = normalizeValue(row.dataset.status);
            var rowCreated = (row.dataset.created || '').trim();

            var matchesSearch = !term || rowSearch.indexOf(term) !== -1;
            var matchesRole = !role || rowRole === role;
            var matchesStatus = !status || rowStatus === status;
            var matchesDate = compareDateRange(rowCreated, fromDate, toDate);

            return matchesSearch && matchesRole && matchesStatus && matchesDate;
        });

        renderUsersTable();
    }

    function setupLazyLoader() {
        if (!lazySentinel) return;

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMoreUsers();
                    }
                });
            }, {
                root: null,
                rootMargin: '220px 0px',
                threshold: 0.01,
            });

            observer.observe(lazySentinel);
            return;
        }

        var scrollHandler = function () {
            var rect = lazySentinel.getBoundingClientRect();
            if (rect.top - window.innerHeight < 220) {
                loadMoreUsers();
            }
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });
        if (tableWrap) {
            tableWrap.addEventListener('scroll', scrollHandler, { passive: true });
        }
    }

    function selectedUserIds() {
        return rows
            .map(function (row) {
                var cb = row.querySelector('.user-select');
                return cb && cb.checked ? parseInt(cb.value, 10) : 0;
            })
            .filter(function (id) { return id > 0; });
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

    rows.forEach(function (row) {
        var cb = row.querySelector('.user-select');
        if (!cb) return;
        cb.addEventListener('change', function () {
            syncSelectAllControl(getVisibleRows());
            updateBulkCount();
        });
    });

    if (selectAllUsers) {
        selectAllUsers.addEventListener('change', function () {
            var visibleRows = getVisibleRows();
            visibleRows.forEach(function (row) {
                var cb = row.querySelector('.user-select');
                if (cb) cb.checked = selectAllUsers.checked;
            });
            updateBulkCount();
            syncSelectAllControl(visibleRows);
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (event) {
            var ids = selectedUserIds();
            var action = bulkActionType ? bulkActionType.value : '';
            if (!action || !ids.length) {
                event.preventDefault();
                window.alert('Select at least one user and choose a bulk action.');
                return;
            }

            if (bulkSelectedInputs) {
                bulkSelectedInputs.innerHTML = '';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_user_ids[]';
                    input.value = String(id);
                    bulkSelectedInputs.appendChild(input);
                });
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () { applyUsersFilter(true); });
    }
    if (roleFilter) {
        roleFilter.addEventListener('change', function () { applyUsersFilter(true); });
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', function () { applyUsersFilter(true); });
    }
    if (dateFrom) {
        dateFrom.addEventListener('change', function () { applyUsersFilter(true); });
    }
    if (dateTo) {
        dateTo.addEventListener('change', function () { applyUsersFilter(true); });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (roleFilter) roleFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';
            applyUsersFilter(true);
            if (searchInput) searchInput.focus();
        });
    }

    var config = document.getElementById('manageUsersConfig');
    if (config) {
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
    }

    initTooltips();
    setupLazyLoader();
    applyUsersFilter(true);
})();
