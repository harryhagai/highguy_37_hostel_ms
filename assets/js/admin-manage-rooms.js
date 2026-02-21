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

    function fillRoomEdit(room) {
        var setValue = function (id, value, fallback) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = value || fallback || '';
        };

        setValue('editRoomId', room.id, '');
        setValue('editRoomHostel', room.hostel_id, '');
        setValue('editRoomNumber', room.room_number, '');
        setValue('editRoomType', room.room_type, '');
        setValue('editRoomPrice', room.price, '0.00');
        setValue('editRoomDescription', room.description, '');
        setValue('editRoomImageId', room.room_image_id, '');
        setValue('editRoomImageLabel', room.room_image_label, '');

        updatePreviewFromPath('editRoomImagePreview', room.room_image_path || '');
    }

    function fillRoomView(room) {
        var setText = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value || '-';
        };

        setText('viewRoomHostel', room.hostel_name);
        setText('viewRoomNumber', room.room_number);
        setText('viewRoomType', room.room_type);

        var price = room.price_display ? 'TZS ' + room.price_display : room.price;
        setText('viewRoomPrice', price || 'TZS 0.00');
        setText('viewRoomCreated', room.created_at_display || room.created_at);
        setText('viewRoomUpdated', room.updated_at_display || room.updated_at);

        var description = (room.description || '').toString().trim();
        setText('viewRoomDescriptionText', description !== '' ? description : '-');

        updatePreviewFromPath('viewRoomImage', room.room_image_path || '');
    }

    function updateResultCount(target, count) {
        if (!target) return;
        target.textContent = count + (count === 1 ? ' result' : ' results');
    }

    function updatePreviewFromPath(imgId, relativePath) {
        var img = document.getElementById(imgId);
        if (!img) return;

        var path = (relativePath || '').toString().trim();
        if (!path) {
            img.src = '';
            img.style.display = 'none';
            return;
        }

        img.src = '../' + path.replace(/^\/+/, '');
        img.style.display = 'block';
    }

    function updatePreviewFromFile(imgId, fileInput) {
        var img = document.getElementById(imgId);
        if (!img || !fileInput || !fileInput.files || !fileInput.files[0]) return;
        var url = URL.createObjectURL(fileInput.files[0]);
        img.src = url;
        img.style.display = 'block';
    }

    function bindImageSelector(selectId, uploadId, previewId) {
        var select = document.getElementById(selectId);
        var upload = document.getElementById(uploadId);

        if (select && select.tagName !== 'SELECT') {
            select = null;
        }

        if (!select && !upload) return;

        if (select) {
            select.addEventListener('change', function () {
                var option = select.options[select.selectedIndex];
                var path = option ? (option.dataset.imagePath || '') : '';
                updatePreviewFromPath(previewId, path);
            });
            var selected = select.options[select.selectedIndex];
            if (selected && selected.dataset.imagePath) {
                updatePreviewFromPath(previewId, selected.dataset.imagePath);
            }
        }

        if (upload) {
            upload.addEventListener('change', function () {
                if (upload.files && upload.files[0]) {
                    updatePreviewFromFile(previewId, upload);
                } else if (select) {
                    var option = select.options[select.selectedIndex];
                    updatePreviewFromPath(previewId, option ? (option.dataset.imagePath || '') : '');
                } else {
                    updatePreviewFromPath(previewId, '');
                }
            });
        }
    }

    var rows = Array.from(document.querySelectorAll('.room-row'));
    var searchInput = document.getElementById('roomsSearchInput');
    var hostelFilter = document.getElementById('roomsHostelFilter');
    var typeFilter = document.getElementById('roomsTypeFilter');
    var priceFilter = document.getElementById('roomsPriceFilter');
    var clearSearch = document.getElementById('clearRoomsSearch');
    var noResultsRow = document.getElementById('roomsNoResultsRow');
    var resultsCount = document.getElementById('roomsResultCount');
    var loadedInfo = document.getElementById('roomsLoadedInfo');
    var selectAllRooms = document.getElementById('selectAllRooms');
    var bulkSelectedCount = document.getElementById('bulkRoomSelectedCount');
    var bulkForm = document.getElementById('bulkRoomsForm');
    var bulkSelectedInputs = document.getElementById('bulkRoomSelectedInputs');
    var bulkActionType = document.getElementById('bulkRoomActionType');
    var lazySentinel = document.getElementById('roomsLazySentinel');
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
            var cb = row.querySelector('.room-select');
            return cb && cb.checked;
        }).length;

        bulkSelectedCount.textContent = selected + ' selected';
    }

    function syncSelectAllControl(visibleRows) {
        if (!selectAllRooms) return;

        if (!visibleRows.length) {
            selectAllRooms.checked = false;
            selectAllRooms.indeterminate = false;
            return;
        }

        var checkedCount = visibleRows.filter(function (row) {
            var cb = row.querySelector('.room-select');
            return cb && cb.checked;
        }).length;

        selectAllRooms.checked = checkedCount === visibleRows.length;
        selectAllRooms.indeterminate = checkedCount > 0 && checkedCount < visibleRows.length;
    }

    function renderRoomsTable() {
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

    function loadMoreRooms() {
        if (loadingMore) return;
        if (state.visibleLimit >= state.filteredRows.length) return;

        loadingMore = true;
        state.visibleLimit = Math.min(state.visibleLimit + state.batchSize, state.filteredRows.length);
        renderRoomsTable();

        window.requestAnimationFrame(function () {
            loadingMore = false;
        });
    }

    function applyRoomsFilter(resetVisible) {
        if (resetVisible) {
            state.visibleLimit = state.batchSize;
        }

        var term = normalizeValue(searchInput ? searchInput.value : '');
        var hostelId = normalizeValue(hostelFilter ? hostelFilter.value : '');
        var roomType = normalizeValue(typeFilter ? typeFilter.value : '');
        var priceTier = normalizeValue(priceFilter ? priceFilter.value : '');

        state.filteredRows = rows.filter(function (row) {
            var rowSearch = normalizeValue(row.dataset.search);
            var rowHostelId = normalizeValue(row.dataset.hostelId);
            var rowType = normalizeValue(row.dataset.roomType);
            var rowPriceTier = normalizeValue(row.dataset.priceTier);

            var matchesSearch = !term || rowSearch.indexOf(term) !== -1;
            var matchesHostel = !hostelId || rowHostelId === hostelId;
            var matchesType = !roomType || rowType === roomType;
            var matchesPrice = !priceTier || rowPriceTier === priceTier;

            return matchesSearch && matchesHostel && matchesType && matchesPrice;
        });

        renderRoomsTable();
    }

    function setupLazyLoader() {
        if (!lazySentinel) return;

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMoreRooms();
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
                loadMoreRooms();
            }
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });
        if (tableWrap) {
            tableWrap.addEventListener('scroll', scrollHandler, { passive: true });
        }
    }

    function selectedRoomIds() {
        return rows
            .map(function (row) {
                var cb = row.querySelector('.room-select');
                return cb && cb.checked ? parseInt(cb.value, 10) : 0;
            })
            .filter(function (id) { return id > 0; });
    }

    document.querySelectorAll('.edit-room-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillRoomEdit(parseJSON(this.dataset.room, {}));
        });
    });

    document.querySelectorAll('.view-room-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillRoomView(parseJSON(this.dataset.room, {}));
        });
    });

    rows.forEach(function (row) {
        var cb = row.querySelector('.room-select');
        if (!cb) return;

        cb.addEventListener('change', function () {
            syncSelectAllControl(getVisibleRows());
            updateBulkCount();
        });
    });

    if (selectAllRooms) {
        selectAllRooms.addEventListener('change', function () {
            var visibleRows = getVisibleRows();
            visibleRows.forEach(function (row) {
                var cb = row.querySelector('.room-select');
                if (cb) cb.checked = selectAllRooms.checked;
            });
            updateBulkCount();
            syncSelectAllControl(visibleRows);
        });
    }

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (event) {
            var ids = selectedRoomIds();
            var action = bulkActionType ? bulkActionType.value : '';
            if (!action || !ids.length) {
                event.preventDefault();
                window.alert('Select at least one room and choose a bulk action.');
                return;
            }

            if (bulkSelectedInputs) {
                bulkSelectedInputs.innerHTML = '';
                ids.forEach(function (id) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_room_ids[]';
                    input.value = String(id);
                    bulkSelectedInputs.appendChild(input);
                });
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () { applyRoomsFilter(true); });
    }
    if (hostelFilter) {
        hostelFilter.addEventListener('change', function () { applyRoomsFilter(true); });
    }
    if (typeFilter) {
        typeFilter.addEventListener('change', function () { applyRoomsFilter(true); });
    }
    if (priceFilter) {
        priceFilter.addEventListener('change', function () { applyRoomsFilter(true); });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (hostelFilter) hostelFilter.value = '';
            if (typeFilter) typeFilter.value = '';
            if (priceFilter) priceFilter.value = '';
            applyRoomsFilter(true);
            if (searchInput) searchInput.focus();
        });
    }

    bindImageSelector('addRoomImageId', 'addRoomImageUpload', 'addRoomImagePreview');
    bindImageSelector('editRoomImageId', 'editRoomImageUpload', 'editRoomImagePreview');

    var config = document.getElementById('manageRoomsConfig');
    if (config) {
        var openModal = config.dataset.openModal || '';
        var editFormData = parseJSON(config.dataset.editForm, null);

        if (openModal === 'editRoomModal' && editFormData) {
            fillRoomEdit(editFormData);
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
    applyRoomsFilter(true);
})();
