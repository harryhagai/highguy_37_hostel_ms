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

    function collectSelectOptions(selectEl) {
        if (!selectEl) return [];
        return Array.from(selectEl.options)
            .filter(function (option) {
                return option.value !== '';
            })
            .map(function (option) {
                return {
                    value: option.value,
                    label: option.textContent || '',
                    hostelId: toInt(option.dataset.hostelId || 0),
                    roomId: toInt(option.dataset.roomId || 0),
                };
            });
    }

    function setupNoticeTargetFlow(prefix) {
        var scopeSelect = document.getElementById(prefix + 'NoticeScope');
        var hostelSelect = document.getElementById(prefix + 'NoticeHostel');
        var roomSelect = document.getElementById(prefix + 'NoticeRoom');
        var bedSelect = document.getElementById(prefix + 'NoticeBed');
        var hostelWrap = document.getElementById(prefix + 'NoticeHostelWrap');
        var roomWrap = document.getElementById(prefix + 'NoticeRoomWrap');
        var bedWrap = document.getElementById(prefix + 'NoticeBedWrap');

        if (!scopeSelect || !hostelSelect || !roomSelect || !bedSelect) {
            return null;
        }

        var allRoomOptions = collectSelectOptions(roomSelect);
        var allBedOptions = collectSelectOptions(bedSelect);

        function findRoomById(roomId) {
            var targetId = toInt(roomId);
            return allRoomOptions.find(function (room) {
                return toInt(room.value) === targetId;
            }) || null;
        }

        function findBedById(bedId) {
            var targetId = toInt(bedId);
            return allBedOptions.find(function (bed) {
                return toInt(bed.value) === targetId;
            }) || null;
        }

        function renderRoomOptions(hostelId, selectedRoomId) {
            var currentHostelId = toInt(hostelId);
            var selectedId = toInt(selectedRoomId);
            var placeholder = roomSelect.dataset.placeholder || 'Select Room';

            roomSelect.innerHTML = '';
            var base = document.createElement('option');
            base.value = '';
            base.textContent = currentHostelId > 0 ? placeholder : 'Select Hostel First';
            roomSelect.appendChild(base);

            var filtered = allRoomOptions.filter(function (room) {
                return currentHostelId > 0 && room.hostelId === currentHostelId;
            });

            filtered.forEach(function (room) {
                var option = document.createElement('option');
                option.value = String(room.value);
                option.textContent = room.label;
                option.dataset.hostelId = String(room.hostelId);
                roomSelect.appendChild(option);
            });

            roomSelect.disabled = currentHostelId <= 0;
            roomSelect.value = filtered.some(function (room) { return toInt(room.value) === selectedId; })
                ? String(selectedId)
                : '';
        }

        function renderBedOptions(hostelId, roomId, selectedBedId) {
            var currentHostelId = toInt(hostelId);
            var currentRoomId = toInt(roomId);
            var selectedId = toInt(selectedBedId);
            var placeholder = bedSelect.dataset.placeholder || 'Select Bed';

            bedSelect.innerHTML = '';
            var base = document.createElement('option');
            base.value = '';
            base.textContent = currentRoomId > 0 ? placeholder : 'Select Room First';
            bedSelect.appendChild(base);

            var filtered = allBedOptions.filter(function (bed) {
                return currentHostelId > 0
                    && currentRoomId > 0
                    && bed.hostelId === currentHostelId
                    && bed.roomId === currentRoomId;
            });

            filtered.forEach(function (bed) {
                var option = document.createElement('option');
                option.value = String(bed.value);
                option.textContent = bed.label;
                option.dataset.hostelId = String(bed.hostelId);
                option.dataset.roomId = String(bed.roomId);
                bedSelect.appendChild(option);
            });

            bedSelect.disabled = currentRoomId <= 0;
            bedSelect.value = filtered.some(function (bed) { return toInt(bed.value) === selectedId; })
                ? String(selectedId)
                : '';
        }

        function applyScopeUi(scope, keepValues) {
            var value = normalizeValue(scope);

            if (hostelWrap) hostelWrap.classList.toggle('d-none', value === 'public');
            if (roomWrap) roomWrap.classList.toggle('d-none', value !== 'room' && value !== 'bed');
            if (bedWrap) bedWrap.classList.toggle('d-none', value !== 'bed');

            hostelSelect.required = value === 'hostel' || value === 'room' || value === 'bed';
            roomSelect.required = value === 'room' || value === 'bed';
            bedSelect.required = value === 'bed';

            if (value === 'public' && !keepValues) {
                hostelSelect.value = '';
                renderRoomOptions(0, 0);
                renderBedOptions(0, 0, 0);
                return;
            }

            if (value === 'hostel' && !keepValues) {
                renderRoomOptions(toInt(hostelSelect.value || 0), 0);
                renderBedOptions(0, 0, 0);
                return;
            }

            if (value === 'room' && !keepValues) {
                renderRoomOptions(toInt(hostelSelect.value || 0), toInt(roomSelect.value || 0));
                renderBedOptions(0, 0, 0);
                return;
            }

            renderRoomOptions(toInt(hostelSelect.value || 0), toInt(roomSelect.value || 0));
            renderBedOptions(
                toInt(hostelSelect.value || 0),
                toInt(roomSelect.value || 0),
                toInt(bedSelect.value || 0)
            );
        }

        function setSelection(scope, hostelId, roomId, bedId) {
            var finalScope = normalizeValue(scope || scopeSelect.value || 'public');
            if (!finalScope) finalScope = 'public';

            var selectedHostelId = toInt(hostelId);
            var selectedRoomId = toInt(roomId);
            var selectedBedId = toInt(bedId);

            if (selectedBedId > 0) {
                var bed = findBedById(selectedBedId);
                if (bed) {
                    if (selectedRoomId <= 0) {
                        selectedRoomId = bed.roomId;
                    }
                    if (selectedHostelId <= 0) {
                        selectedHostelId = bed.hostelId;
                    }
                }
            }

            if (selectedRoomId > 0 && selectedHostelId <= 0) {
                var room = findRoomById(selectedRoomId);
                if (room) {
                    selectedHostelId = room.hostelId;
                }
            }

            scopeSelect.value = finalScope;
            hostelSelect.value = selectedHostelId > 0 ? String(selectedHostelId) : '';
            renderRoomOptions(selectedHostelId, selectedRoomId);
            renderBedOptions(selectedHostelId, selectedRoomId, selectedBedId);
            applyScopeUi(finalScope, true);
        }

        scopeSelect.addEventListener('change', function () {
            applyScopeUi(scopeSelect.value, false);
        });

        hostelSelect.addEventListener('change', function () {
            renderRoomOptions(hostelSelect.value, 0);
            renderBedOptions(hostelSelect.value, 0, 0);
        });

        roomSelect.addEventListener('change', function () {
            var room = findRoomById(roomSelect.value);
            if (room && toInt(hostelSelect.value || 0) !== room.hostelId) {
                hostelSelect.value = String(room.hostelId);
                renderRoomOptions(hostelSelect.value, roomSelect.value);
            }
            renderBedOptions(hostelSelect.value, roomSelect.value, 0);
        });

        bedSelect.addEventListener('change', function () {
            var bed = findBedById(bedSelect.value);
            if (!bed) return;

            if (toInt(hostelSelect.value || 0) !== bed.hostelId) {
                hostelSelect.value = String(bed.hostelId);
            }
            renderRoomOptions(hostelSelect.value, bed.roomId);
            renderBedOptions(hostelSelect.value, bed.roomId, bed.value);
        });

        setSelection(scopeSelect.value, hostelSelect.value, roomSelect.value, bedSelect.value);

        return {
            setSelection: setSelection,
        };
    }

    var editTargetFlow = null;

    function fillNoticeEdit(notice) {
        var id = document.getElementById('editNoticeId');
        var title = document.getElementById('editNoticeTitle');
        var content = document.getElementById('editNoticeContent');

        if (id) id.value = notice.id || '';
        if (title) title.value = notice.title || '';
        if (content) content.value = notice.content || '';
        if (editTargetFlow) {
            editTargetFlow.setSelection(
                notice.target_scope || 'public',
                notice.hostel_id || 0,
                notice.room_id || 0,
                notice.bed_id || 0
            );
        }
    }

    function fillNoticeView(notice) {
        var id = document.getElementById('viewNoticeId');
        var title = document.getElementById('viewNoticeTitle');
        var target = document.getElementById('viewNoticeTarget');
        var created = document.getElementById('viewNoticeCreated');
        var content = document.getElementById('viewNoticeContent');

        if (id) id.textContent = notice.id || '-';
        if (title) title.textContent = notice.title || '-';
        if (target) target.textContent = notice.target_label || 'Public (All Users)';
        if (created) created.textContent = notice.created_at_display || notice.created_at || '-';
        if (content) content.textContent = notice.content || '-';
    }

    function updateResultCount(target, count) {
        if (!target) return;
        target.textContent = count + (count === 1 ? ' result' : ' results');
    }

    function dateBoundary(daysBack) {
        var now = new Date();
        now.setHours(0, 0, 0, 0);
        now.setDate(now.getDate() - daysBack);
        return now;
    }

    function parseDateOnly(value) {
        if (!value) return null;
        var parts = value.split('-');
        if (parts.length !== 3) return null;
        var parsed = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(parsed.getTime())) return null;
        parsed.setHours(0, 0, 0, 0);
        return parsed;
    }

    function matchesDateRange(rowDate, range) {
        if (!range) return true;
        if (!rowDate) return false;

        var today = dateBoundary(0);
        var week = dateBoundary(6);
        var month = dateBoundary(29);

        if (range === 'today') return rowDate.getTime() === today.getTime();
        if (range === 'week') return rowDate >= week && rowDate <= today;
        if (range === 'month') return rowDate >= month && rowDate <= today;
        if (range === 'older') return rowDate < month;

        return true;
    }

    function renderRows(rows, searchInput, dateFilter, noResultsRow, resultsCount) {
        var term = normalizeValue(searchInput ? searchInput.value : '');
        var range = normalizeValue(dateFilter ? dateFilter.value : '');
        var visible = 0;

        rows.forEach(function (row) {
            var rowSearch = normalizeValue(row.dataset.search);
            var rowDate = parseDateOnly(row.dataset.createdDate || '');

            var matchesSearch = !term || rowSearch.indexOf(term) !== -1;
            var matchesDate = matchesDateRange(rowDate, range);
            var show = matchesSearch && matchesDate;

            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', visible !== 0);
        }

        updateResultCount(resultsCount, visible);
    }

    document.querySelectorAll('.edit-notice-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillNoticeEdit(parseJSON(this.dataset.notice, {}));
        });
    });

    document.querySelectorAll('.view-notice-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillNoticeView(parseJSON(this.dataset.notice, {}));
        });
    });

    var rows = Array.from(document.querySelectorAll('.notice-row'));
    var searchInput = document.getElementById('noticesSearchInput');
    var dateFilter = document.getElementById('noticesDateFilter');
    var clearSearch = document.getElementById('clearNoticesSearch');
    var noResultsRow = document.getElementById('noticesNoResultsRow');
    var resultsCount = document.getElementById('noticesResultCount');
    setupNoticeTargetFlow('add');
    editTargetFlow = setupNoticeTargetFlow('edit');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            renderRows(rows, searchInput, dateFilter, noResultsRow, resultsCount);
        });
    }

    if (dateFilter) {
        dateFilter.addEventListener('change', function () {
            renderRows(rows, searchInput, dateFilter, noResultsRow, resultsCount);
        });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (dateFilter) dateFilter.value = '';
            renderRows(rows, searchInput, dateFilter, noResultsRow, resultsCount);
            if (searchInput) searchInput.focus();
        });
    }

    var config = document.getElementById('manageNoticeConfig');
    if (config) {
        var openModal = config.dataset.openModal || '';
        var editFormData = parseJSON(config.dataset.editForm, null);

        if (openModal === 'editNoticeModal' && editFormData) {
            fillNoticeEdit(editFormData);
        }

        if (openModal) {
            var target = document.getElementById(openModal);
            if (target && window.bootstrap) {
                new bootstrap.Modal(target).show();
            }
        }
    }

    initTooltips();
    renderRows(rows, searchInput, dateFilter, noResultsRow, resultsCount);
})();
