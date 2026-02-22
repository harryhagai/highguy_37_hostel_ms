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

    function showWarning(message) {
        if (window.AdminAlerts && typeof window.AdminAlerts.warn === 'function') {
            window.AdminAlerts.warn(message);
            return;
        }
        window.alert(message);
    }

    function fillHostelEdit(hostel) {
        var setValue = function (id, value, fallback) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = value || fallback || '';
        };

        setValue('editHostelId', hostel.id, '');
        setValue('editHostelName', hostel.name, '');
        setValue('editHostelLocation', hostel.location, '');
        setValue('editHostelGender', hostel.gender, 'all');
        setValue('editHostelDescription', hostel.description, '');
        setValue('editHostelExistingImage', hostel.hostel_image, '');

        var preview = document.getElementById('editHostelPreview');
        if (!preview) return;

        if (hostel.hostel_image) {
            preview.src = '../' + hostel.hostel_image;
            preview.style.display = 'inline-block';
        } else {
            preview.src = '';
            preview.style.display = 'none';
        }
    }

    function fillHostelView(hostel) {
        var setText = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value || '-';
        };

        setText('viewHostelName', hostel.name);
        setText('viewHostelLocation', hostel.location);
        var genderValue = normalizeValue(hostel.gender || 'all');
        var genderLabel = genderValue === 'male' ? 'Male Only' : (genderValue === 'female' ? 'Female Only' : 'All Genders');
        setText('viewHostelGender', genderLabel);
        setText('viewHostelRooms', hostel.room_count || '0');
        setText('viewHostelCreated', hostel.created_at_display || hostel.created_at);

        var image = document.getElementById('viewHostelImage');
        if (!image) return;

        if (hostel.hostel_image) {
            image.src = '../' + hostel.hostel_image;
            image.style.display = 'inline-block';
        } else {
            image.src = '';
            image.style.display = 'none';
        }
    }

    function updateResultCount(target, count) {
        if (!target) return;
        target.textContent = count + (count === 1 ? ' result' : ' results');
    }

    var rows = Array.from(document.querySelectorAll('.hostel-row'));
    var searchInput = document.getElementById('hostelsSearchInput');
    var locationFilter = document.getElementById('hostelsLocationFilter');
    var genderFilter = document.getElementById('hostelsGenderFilter');
    var imageFilter = document.getElementById('hostelsImageFilter');
    var clearSearch = document.getElementById('clearHostelsSearch');
    var noResultsRow = document.getElementById('hostelsNoResultsRow');
    var resultsCount = document.getElementById('hostelsResultCount');
    var loadedInfo = document.getElementById('hostelsLoadedInfo');
    var selectAllHostels = document.getElementById('selectAllHostels');
    var bulkSelectedCount = document.getElementById('bulkHostelSelectedCount');
    var bulkForm = document.getElementById('bulkHostelsForm');
    var bulkSelectedInputs = document.getElementById('bulkHostelSelectedInputs');
    var bulkActionType = document.getElementById('bulkHostelActionType');
    var lazySentinel = document.getElementById('hostelsLazySentinel');
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
            var cb = row.querySelector('.hostel-select');
            return cb && cb.checked;
        }).length;

        bulkSelectedCount.textContent = selected + ' selected';
    }

    function syncSelectAllControl(visibleRows) {
        if (!selectAllHostels) return;

        if (!visibleRows.length) {
            selectAllHostels.checked = false;
            selectAllHostels.indeterminate = false;
            return;
        }

        var checkedCount = visibleRows.filter(function (row) {
            var cb = row.querySelector('.hostel-select');
            return cb && cb.checked;
        }).length;

        selectAllHostels.checked = checkedCount === visibleRows.length;
        selectAllHostels.indeterminate = checkedCount > 0 && checkedCount < visibleRows.length;
    }

    function renderHostelsTable() {
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

    function loadMoreHostels() {
        if (loadingMore) return;
        if (state.visibleLimit >= state.filteredRows.length) return;

        loadingMore = true;
        state.visibleLimit = Math.min(state.visibleLimit + state.batchSize, state.filteredRows.length);
        renderHostelsTable();

        window.requestAnimationFrame(function () {
            loadingMore = false;
        });
    }

    function applyHostelsFilter(resetVisible) {
        if (resetVisible) {
            state.visibleLimit = state.batchSize;
        }

        var term = normalizeValue(searchInput ? searchInput.value : '');
        var location = normalizeValue(locationFilter ? locationFilter.value : '');
        var gender = normalizeValue(genderFilter ? genderFilter.value : '');
        var hasImage = normalizeValue(imageFilter ? imageFilter.value : '');

        state.filteredRows = rows.filter(function (row) {
            var rowSearch = normalizeValue(row.dataset.search);
            var rowLocation = normalizeValue(row.dataset.location);
            var rowGender = normalizeValue(row.dataset.gender);
            var rowHasImage = normalizeValue(row.dataset.hasImage);

            var matchesSearch = !term || rowSearch.indexOf(term) !== -1;
            var matchesLocation = !location || rowLocation === location;
            var matchesGender = !gender || rowGender === gender;
            var matchesImage = !hasImage || rowHasImage === hasImage;

            return matchesSearch && matchesLocation && matchesGender && matchesImage;
        });

        renderHostelsTable();
    }

    function setupLazyLoader() {
        if (!lazySentinel) return;

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMoreHostels();
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
                loadMoreHostels();
            }
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });
        if (tableWrap) {
            tableWrap.addEventListener('scroll', scrollHandler, { passive: true });
        }
    }

    function selectedHostelIds() {
        return rows
            .map(function (row) {
                var cb = row.querySelector('.hostel-select');
                return cb && cb.checked ? parseInt(cb.value, 10) : 0;
            })
            .filter(function (id) { return id > 0; });
    }

    document.querySelectorAll('.edit-hostel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillHostelEdit(parseJSON(this.dataset.hostel, {}));
        });
    });

    document.querySelectorAll('.view-hostel-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillHostelView(parseJSON(this.dataset.hostel, {}));
        });
    });

    rows.forEach(function (row) {
        var cb = row.querySelector('.hostel-select');
        if (!cb) return;

        cb.addEventListener('change', function () {
            syncSelectAllControl(getVisibleRows());
            updateBulkCount();
        });
    });

    if (selectAllHostels) {
        selectAllHostels.addEventListener('change', function () {
            var visibleRows = getVisibleRows();
            visibleRows.forEach(function (row) {
                var cb = row.querySelector('.hostel-select');
                if (cb) cb.checked = selectAllHostels.checked;
            });
            updateBulkCount();
            syncSelectAllControl(visibleRows);
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (event) {
            var ids = selectedHostelIds();
            var action = bulkActionType ? bulkActionType.value : '';
            if (!action || !ids.length) {
                event.preventDefault();
                bulkForm.dataset.skipSwalConfirm = '1';
                showWarning('Select at least one hostel and choose a bulk action.');
                return;
            }

            if (bulkSelectedInputs) {
                bulkSelectedInputs.innerHTML = '';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_hostel_ids[]';
                    input.value = String(id);
                    bulkSelectedInputs.appendChild(input);
                });
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () { applyHostelsFilter(true); });
    }
    if (locationFilter) {
        locationFilter.addEventListener('change', function () { applyHostelsFilter(true); });
    }
    if (genderFilter) {
        genderFilter.addEventListener('change', function () { applyHostelsFilter(true); });
    }
    if (imageFilter) {
        imageFilter.addEventListener('change', function () { applyHostelsFilter(true); });
    }
    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (locationFilter) locationFilter.value = '';
            if (genderFilter) genderFilter.value = '';
            if (imageFilter) imageFilter.value = '';
            applyHostelsFilter(true);
            if (searchInput) searchInput.focus();
        });
    }

    var config = document.getElementById('manageHostelConfig');
    if (config) {
        var openModal = config.dataset.openModal || '';
        var editFormData = parseJSON(config.dataset.editForm, null);

        if (openModal === 'editHostelModal' && editFormData) {
            fillHostelEdit(editFormData);
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
    applyHostelsFilter(true);
})();
