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

    function initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;
        document.querySelectorAll('[data-bs-toggle-tooltip="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el);
        });
    }

    function formatStatusLabel(status) {
        var value = normalizeValue(status);
        if (value === 'maintenance') return 'Maintenance';
        if (value === 'inactive') return 'Inactive';
        return 'Active';
    }

    function fillBedEdit(bed) {
        var setValue = function (id, value, fallback) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = value || fallback || '';
        };

        setValue('editBedId', bed.id, '');
        setValue('editBedRoom', bed.room_id, '');
        setValue('editBedNumber', bed.bed_number, '');
        setValue('editBedStatus', bed.status, 'active');
    }

    function fillBedView(bed) {
        var setText = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value || '-';
        };

        setText('viewBedId', bed.id);
        setText('viewBedHostel', bed.hostel_name);
        setText('viewBedRoom', bed.room_number);
        setText('viewBedNumber', bed.bed_number);
        setText('viewBedStatus', formatStatusLabel(bed.status));
        setText('viewBedCreated', bed.created_at_display || bed.created_at);
        setText('viewBedUpdated', bed.updated_at_display || bed.updated_at);
    }

    function updateResultCount(target, count) {
        if (!target) return;
        target.textContent = count + (count === 1 ? ' result' : ' results');
    }

    var rows = Array.from(document.querySelectorAll('.bed-row'));
    var searchInput = document.getElementById('bedsSearchInput');
    var hostelFilter = document.getElementById('bedsHostelFilter');
    var roomFilter = document.getElementById('bedsRoomFilter');
    var statusFilter = document.getElementById('bedsStatusFilter');
    var clearSearch = document.getElementById('clearBedsSearch');
    var noResultsRow = document.getElementById('bedsNoResultsRow');
    var resultsCount = document.getElementById('bedsResultCount');
    var loadedInfo = document.getElementById('bedsLoadedInfo');
    var selectAllBeds = document.getElementById('selectAllBeds');
    var bulkSelectedCount = document.getElementById('bulkBedSelectedCount');
    var bulkForm = document.getElementById('bulkBedsForm');
    var bulkSelectedInputs = document.getElementById('bulkBedSelectedInputs');
    var bulkActionType = document.getElementById('bulkBedActionType');
    var lazySentinel = document.getElementById('bedsLazySentinel');
    var tableWrap = document.querySelector('.users-table-wrap');

    var state = {
        batchSize: 20,
        visibleLimit: 20,
        filteredRows: rows.slice(),
    };

    var loadingMore = false;

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
            var cb = row.querySelector('.bed-select');
            return cb && cb.checked;
        }).length;

        bulkSelectedCount.textContent = selected + ' selected';
    }

    function syncSelectAllControl(visibleRows) {
        if (!selectAllBeds) return;

        if (!visibleRows.length) {
            selectAllBeds.checked = false;
            selectAllBeds.indeterminate = false;
            return;
        }

        var checkedCount = visibleRows.filter(function (row) {
            var cb = row.querySelector('.bed-select');
            return cb && cb.checked;
        }).length;

        selectAllBeds.checked = checkedCount === visibleRows.length;
        selectAllBeds.indeterminate = checkedCount > 0 && checkedCount < visibleRows.length;
    }

    function renderBedsTable() {
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

    function loadMoreBeds() {
        if (loadingMore) return;
        if (state.visibleLimit >= state.filteredRows.length) return;

        loadingMore = true;
        state.visibleLimit = Math.min(state.visibleLimit + state.batchSize, state.filteredRows.length);
        renderBedsTable();

        window.requestAnimationFrame(function () {
            loadingMore = false;
        });
    }

    function applyBedsFilter(resetVisible) {
        if (resetVisible) {
            state.visibleLimit = state.batchSize;
        }

        var term = normalizeValue(searchInput ? searchInput.value : '');
        var hostelId = normalizeValue(hostelFilter ? hostelFilter.value : '');
        var roomId = normalizeValue(roomFilter ? roomFilter.value : '');
        var status = normalizeValue(statusFilter ? statusFilter.value : '');

        state.filteredRows = rows.filter(function (row) {
            var rowSearch = normalizeValue(row.dataset.search);
            var rowHostelId = normalizeValue(row.dataset.hostelId);
            var rowRoomId = normalizeValue(row.dataset.roomId);
            var rowStatus = normalizeValue(row.dataset.status);

            var matchesSearch = !term || rowSearch.indexOf(term) !== -1;
            var matchesHostel = !hostelId || rowHostelId === hostelId;
            var matchesRoom = !roomId || rowRoomId === roomId;
            var matchesStatus = !status || rowStatus === status;

            return matchesSearch && matchesHostel && matchesRoom && matchesStatus;
        });

        renderBedsTable();
    }

    function setupLazyLoader() {
        if (!lazySentinel) return;

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMoreBeds();
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
                loadMoreBeds();
            }
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });
        if (tableWrap) {
            tableWrap.addEventListener('scroll', scrollHandler, { passive: true });
        }
    }

    function selectedBedIds() {
        return rows
            .map(function (row) {
                var cb = row.querySelector('.bed-select');
                return cb && cb.checked ? parseInt(cb.value, 10) : 0;
            })
            .filter(function (id) { return id > 0; });
    }

    document.querySelectorAll('.edit-bed-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillBedEdit(parseJSON(this.dataset.bed, {}));
        });
    });

    document.querySelectorAll('.view-bed-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillBedView(parseJSON(this.dataset.bed, {}));
        });
    });

    rows.forEach(function (row) {
        var cb = row.querySelector('.bed-select');
        if (!cb) return;

        cb.addEventListener('change', function () {
            syncSelectAllControl(getVisibleRows());
            updateBulkCount();
        });
    });

    if (selectAllBeds) {
        selectAllBeds.addEventListener('change', function () {
            var visibleRows = getVisibleRows();
            visibleRows.forEach(function (row) {
                var cb = row.querySelector('.bed-select');
                if (cb) cb.checked = selectAllBeds.checked;
            });
            updateBulkCount();
            syncSelectAllControl(visibleRows);
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (event) {
            var ids = selectedBedIds();
            var action = bulkActionType ? bulkActionType.value : '';
            if (!action || !ids.length) {
                event.preventDefault();
                window.alert('Select at least one bed and choose a bulk action.');
                return;
            }

            if (bulkSelectedInputs) {
                bulkSelectedInputs.innerHTML = '';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_bed_ids[]';
                    input.value = String(id);
                    bulkSelectedInputs.appendChild(input);
                });
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () { applyBedsFilter(true); });
    }
    if (hostelFilter) {
        hostelFilter.addEventListener('change', function () { applyBedsFilter(true); });
    }
    if (roomFilter) {
        roomFilter.addEventListener('change', function () { applyBedsFilter(true); });
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', function () { applyBedsFilter(true); });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (hostelFilter) hostelFilter.value = '';
            if (roomFilter) roomFilter.value = '';
            if (statusFilter) statusFilter.value = '';
            applyBedsFilter(true);
            if (searchInput) searchInput.focus();
        });
    }

    var config = document.getElementById('manageBedsConfig');
    if (config) {
        var openModal = config.dataset.openModal || '';
        var editFormData = parseJSON(config.dataset.editForm, null);

        if (openModal === 'editBedModal' && editFormData) {
            fillBedEdit(editFormData);
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
    applyBedsFilter(true);
})();
