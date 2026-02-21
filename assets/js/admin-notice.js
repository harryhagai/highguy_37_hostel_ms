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

    function fillNoticeEdit(notice) {
        var id = document.getElementById('editNoticeId');
        var title = document.getElementById('editNoticeTitle');
        var content = document.getElementById('editNoticeContent');

        if (id) id.value = notice.id || '';
        if (title) title.value = notice.title || '';
        if (content) content.value = notice.content || '';
    }

    function fillNoticeView(notice) {
        var id = document.getElementById('viewNoticeId');
        var title = document.getElementById('viewNoticeTitle');
        var created = document.getElementById('viewNoticeCreated');
        var content = document.getElementById('viewNoticeContent');

        if (id) id.textContent = notice.id || '-';
        if (title) title.textContent = notice.title || '-';
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
