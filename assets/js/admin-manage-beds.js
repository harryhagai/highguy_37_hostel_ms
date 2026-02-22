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

    function toInt(value) {
        var number = parseInt(value, 10);
        return isNaN(number) ? 0 : number;
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

    function bedPrefixFromSelect(hostelSelect) {
        if (!hostelSelect || hostelSelect.selectedIndex < 0) return '';
        var option = hostelSelect.options[hostelSelect.selectedIndex];
        if (!option || !option.value) return '';
        var hostelName = option.dataset.hostelName || option.textContent || '';
        return buildHostelCode(hostelName, 'BD') + '/B/';
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

    function syncBedNumberPrefix(hostelSelect, bedNumberInput, forcePrefix) {
        if (!hostelSelect || !bedNumberInput) return;
        var prefix = bedPrefixFromSelect(hostelSelect);
        if (!prefix) return;

        if (forcePrefix) {
            applyAutoprefix(bedNumberInput, prefix, 'B');
            return;
        }

        var current = (bedNumberInput.value || '').trim();
        if (!current) {
            bedNumberInput.value = prefix;
            bedNumberInput.dataset.autoPrefix = prefix;
            return;
        }

        if (current.toUpperCase().indexOf(prefix.toUpperCase()) === 0) {
            bedNumberInput.dataset.autoPrefix = prefix;
        }
    }

    function collectRoomOptions(roomSelect) {
        if (!roomSelect) return [];
        return Array.from(roomSelect.options)
            .filter(function (option) {
                return option.value !== '';
            })
            .map(function (option) {
                return {
                    id: toInt(option.value),
                    hostelId: toInt(option.dataset.hostelId || ''),
                    label: option.textContent || '',
                };
            });
    }

    function inferHostelIdFromRoom(roomOptions, roomId) {
        var targetRoomId = toInt(roomId);
        if (targetRoomId <= 0) return 0;
        for (var i = 0; i < roomOptions.length; i += 1) {
            if (roomOptions[i].id === targetRoomId) {
                return roomOptions[i].hostelId;
            }
        }
        return 0;
    }

    function collectSelectedRoomIds(roomSelect) {
        if (!roomSelect) return [];
        if (!roomSelect.multiple) {
            var singleId = toInt(roomSelect.value || 0);
            return singleId > 0 ? [singleId] : [];
        }

        return Array.from(roomSelect.selectedOptions || [])
            .map(function (option) { return toInt(option.value || 0); })
            .filter(function (id) { return id > 0; });
    }

    function setupBedFormFlow(hostelSelect, roomSelect, bedNumberInput) {
        if (!hostelSelect || !roomSelect || !bedNumberInput) {
            return null;
        }

        var roomOptions = collectRoomOptions(roomSelect);
        var isMultiple = !!roomSelect.multiple;

        function normalizeSelectedRooms(selectedRoomIds) {
            if (Array.isArray(selectedRoomIds)) {
                var ids = selectedRoomIds
                    .map(function (value) { return toInt(value); })
                    .filter(function (id) { return id > 0; });
                return Array.from(new Set(ids));
            }
            var single = toInt(selectedRoomIds);
            return single > 0 ? [single] : [];
        }

        function renderRoomOptions(hostelId, selectedRoomIds) {
            var currentHostelId = toInt(hostelId);
            var selectedIds = normalizeSelectedRooms(selectedRoomIds);
            var placeholderText = roomSelect.dataset.placeholder || 'Select Room';

            roomSelect.innerHTML = '';

            if (!isMultiple) {
                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = currentHostelId > 0 ? placeholderText : 'Select Hostel First';
                roomSelect.appendChild(placeholder);
            }

            var filtered = roomOptions.filter(function (option) {
                return currentHostelId > 0 && option.hostelId === currentHostelId;
            });

            filtered.forEach(function (optionData) {
                var option = document.createElement('option');
                option.value = String(optionData.id);
                option.textContent = optionData.label;
                option.dataset.hostelId = String(optionData.hostelId);
                roomSelect.appendChild(option);
            });

            roomSelect.disabled = currentHostelId <= 0;

            if (isMultiple) {
                var selectedMap = {};
                selectedIds.forEach(function (id) {
                    selectedMap[id] = true;
                });
                Array.from(roomSelect.options).forEach(function (option) {
                    var optionId = toInt(option.value || 0);
                    option.selected = !!selectedMap[optionId];
                });
                return;
            }

            var currentRoomId = selectedIds.length ? selectedIds[0] : 0;
            var hasCurrentRoom = filtered.some(function (optionData) {
                return optionData.id === currentRoomId;
            });
            roomSelect.value = hasCurrentRoom ? String(currentRoomId) : '';
        }

        function setSelection(hostelId, roomIdOrIds) {
            var currentHostelId = toInt(hostelId);
            var selectedRoomIds = normalizeSelectedRooms(roomIdOrIds);
            var currentRoomId = selectedRoomIds.length ? selectedRoomIds[0] : 0;

            if (currentHostelId <= 0 && currentRoomId > 0) {
                currentHostelId = inferHostelIdFromRoom(roomOptions, currentRoomId);
            }

            hostelSelect.value = currentHostelId > 0 ? String(currentHostelId) : '';
            renderRoomOptions(currentHostelId, selectedRoomIds);
            syncBedNumberPrefix(hostelSelect, bedNumberInput, false);
        }

        hostelSelect.addEventListener('change', function () {
            renderRoomOptions(hostelSelect.value, []);
            syncBedNumberPrefix(hostelSelect, bedNumberInput, true);
        });

        roomSelect.addEventListener('change', function () {
            if (!isMultiple) {
                var option = roomSelect.options[roomSelect.selectedIndex];
                var optionHostelId = toInt(option ? option.dataset.hostelId : 0);
                if (optionHostelId > 0 && toInt(hostelSelect.value) !== optionHostelId) {
                    hostelSelect.value = String(optionHostelId);
                }
            }
            syncBedNumberPrefix(hostelSelect, bedNumberInput, false);
        });

        setSelection(hostelSelect.value, collectSelectedRoomIds(roomSelect));

        return {
            setSelection: setSelection,
        };
    }

    function setupBedsRoomFilterFlow(hostelFilterSelect, roomFilterSelect) {
        if (!hostelFilterSelect || !roomFilterSelect) {
            return null;
        }

        var allRoomOptions = Array.from(roomFilterSelect.options)
            .filter(function (option) {
                return option.value !== '';
            })
            .map(function (option) {
                return {
                    value: option.value,
                    label: option.textContent || '',
                    hostelId: toInt(option.dataset.hostelId || 0),
                };
            });

        function render(hostelId, selectedRoomId) {
            var currentHostelId = toInt(hostelId);
            var currentRoomValue = selectedRoomId ? String(selectedRoomId) : '';

            roomFilterSelect.innerHTML = '';

            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = currentHostelId > 0 ? 'All Rooms' : 'Select Hostel First';
            roomFilterSelect.appendChild(placeholder);

            if (currentHostelId > 0) {
                allRoomOptions.forEach(function (optionData) {
                    if (optionData.hostelId !== currentHostelId) return;
                    var option = document.createElement('option');
                    option.value = String(optionData.value);
                    option.textContent = optionData.label;
                    option.dataset.hostelId = String(optionData.hostelId);
                    roomFilterSelect.appendChild(option);
                });
            }

            roomFilterSelect.disabled = currentHostelId <= 0;

            var canUseCurrentRoom = currentHostelId > 0 && allRoomOptions.some(function (optionData) {
                return optionData.hostelId === currentHostelId && String(optionData.value) === currentRoomValue;
            });
            roomFilterSelect.value = canUseCurrentRoom ? currentRoomValue : '';
        }

        function setSelection(hostelId, roomId) {
            var currentHostelId = toInt(hostelId);
            hostelFilterSelect.value = currentHostelId > 0 ? String(currentHostelId) : '';
            render(currentHostelId, roomId);
        }

        setSelection(hostelFilterSelect.value, roomFilterSelect.value);

        return {
            render: render,
            setSelection: setSelection,
        };
    }

    function findRoomOptionById(roomSelect, roomId) {
        if (!roomSelect) return null;
        var targetId = toInt(roomId);
        if (targetId <= 0) return null;

        for (var i = 0; i < roomSelect.options.length; i += 1) {
            var option = roomSelect.options[i];
            if (toInt(option.value || 0) === targetId) {
                return option;
            }
        }

        return null;
    }

    function getAddBulkCountMax(roomSelect) {
        var selectedRoomIds = collectSelectedRoomIds(roomSelect);
        if (!selectedRoomIds.length) return 1;

        var minAvailable = null;
        selectedRoomIds.forEach(function (roomId) {
            var option = findRoomOptionById(roomSelect, roomId);
            var capacity = toInt(option && option.dataset ? option.dataset.bedCapacity || 0 : 0);
            if (capacity <= 0) {
                capacity = 4;
            }

            var existing = toInt(existingBedsByRoom[String(roomId)] || 0);
            var available = capacity - existing;
            if (available < 1) {
                available = 1;
            }

            if (minAvailable === null || available < minAvailable) {
                minAvailable = available;
            }
        });

        if (minAvailable === null || minAvailable < 1) {
            return 1;
        }

        return minAvailable;
    }

    function syncBedAddModeUi(modeSelect, bulkWrap, bulkInput) {
        if (!modeSelect || !bulkWrap || !bulkInput) return;
        var dynamicMax = getAddBulkCountMax(addBedRoomSelect);
        bulkInput.min = '1';
        bulkInput.max = String(dynamicMax);
        var isBulk = normalizeValue(modeSelect.value) === 'bulk';
        bulkWrap.classList.toggle('d-none', !isBulk);
        bulkInput.required = isBulk;
        syncBulkModalWidth(modeSelect, isBulk);
        if (!isBulk) {
            bulkInput.value = '1';
            return;
        }

        var count = parseInt(bulkInput.value, 10);
        if (isNaN(count) || count < 1) {
            bulkInput.value = '1';
        } else if (count > dynamicMax) {
            bulkInput.value = String(dynamicMax);
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
        var maxCount = parseInt(countInput.max, 10);
        if (isNaN(maxCount) || maxCount < 1) {
            maxCount = 200;
        }
        if (isNaN(count) || count < 1) {
            count = 1;
        } else if (count > maxCount) {
            count = maxCount;
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

    function formatStatusLabel(status) {
        var value = normalizeValue(status);
        if (value === 'maintenance') return 'Maintenance';
        if (value === 'inactive') return 'Inactive';
        return 'Active';
    }

    var editBedFlow = null;
    var existingBedsByRoom = {};

    function fillBedEdit(bed) {
        var setValue = function (id, value, fallback) {
            var el = document.getElementById(id);
            if (!el) return;
            el.value = value || fallback || '';
        };

        setValue('editBedId', bed.id, '');
        setValue('editBedHostel', bed.hostel_id, '');
        setValue('editBedRoom', bed.room_id, '');
        setValue('editBedNumber', bed.bed_number, '');
        setValue('editBedStatus', bed.status, 'active');

        if (editBedFlow) {
            editBedFlow.setSelection(bed.hostel_id, bed.room_id);
        }
        syncBedNumberPrefix(
            document.getElementById('editBedHostel'),
            document.getElementById('editBedNumber'),
            false
        );
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
    var addBedHostelSelect = document.getElementById('addBedHostel');
    var addBedRoomSelect = document.getElementById('addBedRoom');
    var addBedNumberInput = document.getElementById('addBedNumber');
    var addBedModeSelect = document.getElementById('addBedMode');
    var addBedBulkCountWrap = document.getElementById('addBedBulkCountWrap');
    var addBedBulkCountInput = document.getElementById('addBedBulkCount');
    var addBedBulkPreviewWrap = document.getElementById('addBedBulkPreviewWrap');
    var addBedBulkPreviewList = document.getElementById('addBedBulkPreviewList');
    var addBedBulkPreviewHint = document.getElementById('addBedBulkPreviewHint');
    var editBedHostelSelect = document.getElementById('editBedHostel');
    var editBedRoomSelect = document.getElementById('editBedRoom');
    var editBedNumberInput = document.getElementById('editBedNumber');
    var bedSequenceByRoom = {};

    rows.forEach(function (row) {
        var bed = parseJSON(row.dataset.bed, {});
        var roomIdRaw = bed.room_id || row.dataset.roomId || '';
        var roomIdNumber = toInt(roomIdRaw);
        if (roomIdNumber > 0) {
            var roomIdCountKey = String(roomIdNumber);
            existingBedsByRoom[roomIdCountKey] = toInt(existingBedsByRoom[roomIdCountKey] || 0) + 1;
        }

        var roomIdKey = normalizeValue(bed.room_id || row.dataset.roomId || '');
        if (!roomIdKey) return;

        var parsed = extractTrailingNumber(bed.bed_number || '');
        if (parsed.number <= 0) return;

        var existing = bedSequenceByRoom[roomIdKey];
        if (!existing || parsed.number > existing.max) {
            bedSequenceByRoom[roomIdKey] = { max: parsed.number, width: parsed.width };
            return;
        }

        if (parsed.number === existing.max && parsed.width > existing.width) {
            existing.width = parsed.width;
        }
    });

    var addBedFlow = setupBedFormFlow(
        addBedHostelSelect,
        addBedRoomSelect,
        addBedNumberInput
    );
    editBedFlow = setupBedFormFlow(
        editBedHostelSelect,
        editBedRoomSelect,
        editBedNumberInput
    );
    var bedsFilterFlow = setupBedsRoomFilterFlow(hostelFilter, roomFilter);

    function suggestNextBedNumber(force) {
        if (!addBedHostelSelect || !addBedRoomSelect || !addBedNumberInput) return;
        syncBedAddModeUi(addBedModeSelect, addBedBulkCountWrap, addBedBulkCountInput);

        var prefix = bedPrefixFromSelect(addBedHostelSelect);
        var selectedRoomIds = collectSelectedRoomIds(addBedRoomSelect);
        var roomId = selectedRoomIds.length > 0 ? String(selectedRoomIds[0]) : '';
        if (!prefix) return;

        if (!roomId) {
            if (force) {
                applyAutoprefix(addBedNumberInput, prefix, 'B');
            }
            return;
        }

        var sequence = bedSequenceByRoom[roomId] || { max: 0, width: 1 };
        var nextNumber = sequence.max + 1;
        var paddedNext = String(nextNumber).padStart(Math.max(1, sequence.width), '0');
        var suggested = prefix + paddedNext;

        var current = (addBedNumberInput.value || '').trim();
        if (force || current === '' || current.toUpperCase() === prefix.toUpperCase()) {
            addBedNumberInput.value = suggested;
            addBedNumberInput.dataset.autoPrefix = prefix;
        }

        renderBulkNumberPreview(
            addBedModeSelect,
            addBedNumberInput,
            addBedBulkCountInput,
            addBedBulkPreviewWrap,
            addBedBulkPreviewList,
            addBedBulkPreviewHint,
            'Bed'
        );
    }

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
                bulkForm.dataset.skipSwalConfirm = '1';
                showWarning('Select at least one bed and choose a bulk action.');
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
        hostelFilter.addEventListener('change', function () {
            if (bedsFilterFlow) {
                bedsFilterFlow.render(hostelFilter.value, '');
            }
            applyBedsFilter(true);
        });
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
            if (bedsFilterFlow) {
                bedsFilterFlow.setSelection('', '');
            } else if (roomFilter) {
                roomFilter.value = '';
            }
            if (statusFilter) statusFilter.value = '';
            applyBedsFilter(true);
            if (searchInput) searchInput.focus();
        });
    }

    if (addBedHostelSelect) {
        addBedHostelSelect.addEventListener('change', function () {
            suggestNextBedNumber(true);
        });
    }
    if (addBedRoomSelect) {
        addBedRoomSelect.addEventListener('change', function () {
            suggestNextBedNumber(true);
        });
    }
    if (addBedNumberInput) {
        addBedNumberInput.addEventListener('input', function () {
            renderBulkNumberPreview(
                addBedModeSelect,
                addBedNumberInput,
                addBedBulkCountInput,
                addBedBulkPreviewWrap,
                addBedBulkPreviewList,
                addBedBulkPreviewHint,
                'Bed'
            );
        });
    }

    if (addBedModeSelect && addBedBulkCountWrap && addBedBulkCountInput) {
        addBedModeSelect.addEventListener('change', function () {
            syncBedAddModeUi(addBedModeSelect, addBedBulkCountWrap, addBedBulkCountInput);
            renderBulkNumberPreview(
                addBedModeSelect,
                addBedNumberInput,
                addBedBulkCountInput,
                addBedBulkPreviewWrap,
                addBedBulkPreviewList,
                addBedBulkPreviewHint,
                'Bed'
            );
        });
        addBedBulkCountInput.addEventListener('input', function () {
            syncBedAddModeUi(addBedModeSelect, addBedBulkCountWrap, addBedBulkCountInput);
            renderBulkNumberPreview(
                addBedModeSelect,
                addBedNumberInput,
                addBedBulkCountInput,
                addBedBulkPreviewWrap,
                addBedBulkPreviewList,
                addBedBulkPreviewHint,
                'Bed'
            );
        });
        syncBedAddModeUi(addBedModeSelect, addBedBulkCountWrap, addBedBulkCountInput);
        renderBulkNumberPreview(
            addBedModeSelect,
            addBedNumberInput,
            addBedBulkCountInput,
            addBedBulkPreviewWrap,
            addBedBulkPreviewList,
            addBedBulkPreviewHint,
            'Bed'
        );
    }

    var config = document.getElementById('manageBedsConfig');
    if (config) {
        var openModal = config.dataset.openModal || '';
        var editFormData = parseJSON(config.dataset.editForm, null);
        var initialHostelId = toInt(config.dataset.initialHostelId || '0');
        var initialRoomId = toInt(config.dataset.initialRoomId || '0');

        if (initialHostelId > 0 && hostelFilter) {
            hostelFilter.value = String(initialHostelId);
        }
        if (bedsFilterFlow) {
            bedsFilterFlow.setSelection(initialHostelId, initialRoomId);
        } else if (initialRoomId > 0 && roomFilter) {
            roomFilter.value = String(initialRoomId);
        }
        if (addBedFlow && (initialHostelId > 0 || initialRoomId > 0)) {
            addBedFlow.setSelection(initialHostelId, initialRoomId);
            suggestNextBedNumber(false);
        }

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
    suggestNextBedNumber(false);
    applyBedsFilter(true);
})();
