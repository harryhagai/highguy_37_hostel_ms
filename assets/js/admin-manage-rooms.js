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

    function buildHostelCode(hostelName, fallback) {
        var value = (hostelName || '').toString().trim();
        if (!value) return fallback;

        var words = value
            .replace(/[^A-Za-z0-9\s]+/g, ' ')
            .split(/\s+/)
            .filter(Boolean);

        if (!words.length) return fallback;

        if (words.length === 1) {
            var single = words[0].toUpperCase();
            return single.slice(0, Math.min(2, single.length)) || fallback;
        }

        return (words[0].charAt(0) + words[1].charAt(0)).toUpperCase();
    }

    function roomPrefixFromSelect(hostelSelect) {
        if (!hostelSelect || hostelSelect.selectedIndex < 0) return '';
        var option = hostelSelect.options[hostelSelect.selectedIndex];
        if (!option || !option.value) return '';
        var hostelName = option.dataset.hostelName || option.textContent || '';
        return buildHostelCode(hostelName, 'RM') + '/R/';
    }

    function extractTrailingNumber(value) {
        var text = (value || '').toString().trim();
        var match = text.match(/(\d+)\s*$/);
        if (!match) {
            return { number: 0, width: 1 };
        }

        var parsed = parseInt(match[1], 10);
        if (isNaN(parsed) || parsed <= 0) {
            return { number: 0, width: match[1].length || 1 };
        }

        return {
            number: parsed,
            width: match[1].length || 1,
        };
    }

    function buildGeneratedSeries(seed, count) {
        var value = (seed || '').toString().trim();
        var amount = parseInt(count, 10);
        if (!value) return [];
        if (isNaN(amount) || amount <= 1) return [value];

        var match = value.match(/^(.*?)(\d+)$/);
        if (!match) return [];

        var prefix = match[1];
        var digits = match[2];
        var width = Math.max(1, digits.length);
        var start = parseInt(digits, 10);
        if (isNaN(start)) return [];

        var result = [];
        for (var index = 0; index < amount; index += 1) {
            result.push(prefix + String(start + index).padStart(width, '0'));
        }
        return result;
    }

    function applyAutoprefix(input, prefix, patternLetter) {
        if (!input || !prefix) return;

        var rawValue = (input.value || '').toString();
        var value = rawValue.trim();
        var previousPrefix = input.dataset.autoPrefix || '';

        if (value === '') {
            input.value = prefix;
            input.dataset.autoPrefix = prefix;
            return;
        }

        if (previousPrefix && value.toUpperCase().indexOf(previousPrefix.toUpperCase()) === 0) {
            input.value = prefix + value.slice(previousPrefix.length);
            input.dataset.autoPrefix = prefix;
            return;
        }

        var genericPattern = new RegExp('^[A-Z0-9]{1,4}\\/' + patternLetter + '\\/', 'i');
        if (genericPattern.test(value)) {
            input.value = prefix + value.replace(genericPattern, '');
            input.dataset.autoPrefix = prefix;
        }
    }

    function syncRoomNumberPrefix(hostelSelect, roomNumberInput, forcePrefix) {
        if (!hostelSelect || !roomNumberInput) return;
        var prefix = roomPrefixFromSelect(hostelSelect);
        if (!prefix) return;

        if (forcePrefix) {
            applyAutoprefix(roomNumberInput, prefix, 'R');
            return;
        }

        var current = (roomNumberInput.value || '').trim();
        if (!current) {
            roomNumberInput.value = prefix;
            roomNumberInput.dataset.autoPrefix = prefix;
            return;
        }

        var normalizedCurrent = current.toUpperCase();
        if (normalizedCurrent.indexOf(prefix.toUpperCase()) === 0) {
            roomNumberInput.dataset.autoPrefix = prefix;
        }
    }

    function syncRoomAddModeUi(modeSelect, bulkWrap, bulkInput) {
        if (!modeSelect || !bulkWrap || !bulkInput) return;
        var isBulk = normalizeValue(modeSelect.value) === 'bulk';
        bulkWrap.classList.toggle('d-none', !isBulk);
        bulkInput.required = isBulk;
        syncBulkModalWidth(modeSelect, isBulk);
        if (!isBulk) {
            bulkInput.value = '2';
            return;
        }

        var rawValue = (bulkInput.value || '').toString().trim();
        if (rawValue === '') {
            // Allow clearing while typing (e.g. to enter 40) without forcing "2" back immediately.
            return;
        }

        var count = parseInt(bulkInput.value, 10);
        if (isNaN(count) || count < 2) {
            bulkInput.value = '2';
        } else if (count > 200) {
            bulkInput.value = '200';
        }
    }

    function syncBulkModalWidth(modeSelect, isBulk) {
        if (!modeSelect) return;
        var modal = modeSelect.closest('.modal');
        if (!modal) return;
        var dialog = modal.querySelector('.modal-dialog');
        if (!dialog) return;
        dialog.classList.toggle('modal-bulk-wide', !!isBulk);
    }

    function renderBulkNumberPreview(modeSelect, seedInput, countInput, wrap, list, hint, entityName) {
        if (!modeSelect || !seedInput || !countInput || !wrap || !list || !hint) return;

        var isBulk = normalizeValue(modeSelect.value) === 'bulk';
        wrap.classList.toggle('d-none', !isBulk);
        if (!isBulk) {
            list.innerHTML = '';
            return;
        }

        var count = parseInt(countInput.value, 10);
        if (isNaN(count) || count < 2) {
            count = 2;
        } else if (count > 200) {
            count = 200;
        }

        var series = buildGeneratedSeries(seedInput.value || '', count);
        if (!series.length) {
            hint.textContent = entityName + ' number must end with digits for bulk generation.';
            list.innerHTML = '';
            return;
        }

        hint.textContent = 'Preview of ' + Math.min(series.length, 12) + ' of ' + series.length + ' generated numbers.';
        list.innerHTML = series.slice(0, 12).map(function (value) {
            return '<span class="badge text-bg-light border">' + value + '</span>';
        }).join('');
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
        setValue('editRoomBedCapacity', room.bed_capacity, '4');
        setValue('editRoomPrice', room.price, '0.00');
        setValue('editRoomDescription', room.description, '');
        setValue('editRoomImageId', room.room_image_id, '');
        setValue('editRoomImageLabel', room.room_image_label, '');

        updatePreviewFromPath('editRoomImagePreview', room.room_image_path || '');
        syncRoomNumberPrefix(
            document.getElementById('editRoomHostel'),
            document.getElementById('editRoomNumber'),
            false
        );
    }

    function fillRoomView(room) {
        var setText = function (id, value) {
            var el = document.getElementById(id);
            if (el) el.textContent = value || '-';
        };

        setText('viewRoomHostel', room.hostel_name);
        setText('viewRoomNumber', room.room_number);
        setText('viewRoomType', room.room_type);
        setText('viewRoomBedCapacity', room.bed_capacity || '4');

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
    var addRoomHostelSelect = document.getElementById('addRoomHostel');
    var addRoomNumberInput = document.getElementById('addRoomNumber');
    var addRoomModeSelect = document.getElementById('addRoomMode');
    var addRoomBulkCountWrap = document.getElementById('addRoomBulkCountWrap');
    var addRoomBulkCountInput = document.getElementById('addRoomBulkCount');
    var addRoomBulkPreviewWrap = document.getElementById('addRoomBulkPreviewWrap');
    var addRoomBulkPreviewList = document.getElementById('addRoomBulkPreviewList');
    var addRoomBulkPreviewHint = document.getElementById('addRoomBulkPreviewHint');
    var editRoomHostelSelect = document.getElementById('editRoomHostel');
    var editRoomNumberInput = document.getElementById('editRoomNumber');
    var roomSequenceMap = {};

    rows.forEach(function (row) {
        var room = parseJSON(row.dataset.room, {});
        var hostelId = normalizeValue(room.hostel_id || row.dataset.hostelId || '');
        if (!hostelId) return;

        var parsed = extractTrailingNumber(room.room_number || '');
        if (parsed.number <= 0) return;

        var existing = roomSequenceMap[hostelId];
        if (!existing || parsed.number > existing.max) {
            roomSequenceMap[hostelId] = { max: parsed.number, width: parsed.width };
            return;
        }

        if (parsed.number === existing.max && parsed.width > existing.width) {
            existing.width = parsed.width;
        }
    });

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
                bulkForm.dataset.skipSwalConfirm = '1';
                showWarning('Select at least one room and choose a bulk action.');
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

    if (addRoomHostelSelect && addRoomNumberInput) {
        var suggestNextRoomNumber = function (force) {
            var prefix = roomPrefixFromSelect(addRoomHostelSelect);
            var hostelId = normalizeValue(addRoomHostelSelect.value || '');
            if (!prefix || !hostelId) return;

            var sequence = roomSequenceMap[hostelId] || { max: 0, width: 1 };
            var nextNumber = sequence.max + 1;
            var paddedNext = String(nextNumber).padStart(Math.max(1, sequence.width), '0');
            var suggested = prefix + paddedNext;

            var current = (addRoomNumberInput.value || '').trim();
            if (force || current === '' || current.toUpperCase() === prefix.toUpperCase()) {
                addRoomNumberInput.value = suggested;
                addRoomNumberInput.dataset.autoPrefix = prefix;
            }

            renderBulkNumberPreview(
                addRoomModeSelect,
                addRoomNumberInput,
                addRoomBulkCountInput,
                addRoomBulkPreviewWrap,
                addRoomBulkPreviewList,
                addRoomBulkPreviewHint,
                'Room'
            );
        };

        addRoomHostelSelect.addEventListener('change', function () {
            suggestNextRoomNumber(true);
        });

        if ((addRoomNumberInput.value || '').trim() === '') {
            suggestNextRoomNumber(true);
        } else {
            syncRoomNumberPrefix(addRoomHostelSelect, addRoomNumberInput, false);
            renderBulkNumberPreview(
                addRoomModeSelect,
                addRoomNumberInput,
                addRoomBulkCountInput,
                addRoomBulkPreviewWrap,
                addRoomBulkPreviewList,
                addRoomBulkPreviewHint,
                'Room'
            );
        }

        addRoomNumberInput.addEventListener('input', function () {
            renderBulkNumberPreview(
                addRoomModeSelect,
                addRoomNumberInput,
                addRoomBulkCountInput,
                addRoomBulkPreviewWrap,
                addRoomBulkPreviewList,
                addRoomBulkPreviewHint,
                'Room'
            );
        });
    }

    if (addRoomModeSelect && addRoomBulkCountWrap && addRoomBulkCountInput) {
        addRoomModeSelect.addEventListener('change', function () {
            syncRoomAddModeUi(addRoomModeSelect, addRoomBulkCountWrap, addRoomBulkCountInput);
            renderBulkNumberPreview(
                addRoomModeSelect,
                addRoomNumberInput,
                addRoomBulkCountInput,
                addRoomBulkPreviewWrap,
                addRoomBulkPreviewList,
                addRoomBulkPreviewHint,
                'Room'
            );
        });
        addRoomBulkCountInput.addEventListener('input', function () {
            syncRoomAddModeUi(addRoomModeSelect, addRoomBulkCountWrap, addRoomBulkCountInput);
            renderBulkNumberPreview(
                addRoomModeSelect,
                addRoomNumberInput,
                addRoomBulkCountInput,
                addRoomBulkPreviewWrap,
                addRoomBulkPreviewList,
                addRoomBulkPreviewHint,
                'Room'
            );
        });
        syncRoomAddModeUi(addRoomModeSelect, addRoomBulkCountWrap, addRoomBulkCountInput);
        renderBulkNumberPreview(
            addRoomModeSelect,
            addRoomNumberInput,
            addRoomBulkCountInput,
            addRoomBulkPreviewWrap,
            addRoomBulkPreviewList,
            addRoomBulkPreviewHint,
            'Room'
        );
    }

    if (editRoomHostelSelect && editRoomNumberInput) {
        editRoomHostelSelect.addEventListener('change', function () {
            syncRoomNumberPrefix(editRoomHostelSelect, editRoomNumberInput, true);
        });
        syncRoomNumberPrefix(editRoomHostelSelect, editRoomNumberInput, false);
    }

    bindImageSelector('addRoomImageId', 'addRoomImageUpload', 'addRoomImagePreview');
    bindImageSelector('editRoomImageId', 'editRoomImageUpload', 'editRoomImagePreview');

    var config = document.getElementById('manageRoomsConfig');
    if (config) {
        var openModal = config.dataset.openModal || '';
        var editFormData = parseJSON(config.dataset.editForm, null);
        var initialHostelId = normalizeValue(config.dataset.initialHostelId || '');

        if (initialHostelId && hostelFilter) {
            hostelFilter.value = initialHostelId;
        }

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
