(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function normalize(value) {
        return (value || '').toString().toLowerCase().trim();
    }

    function setText(id, value) {
        var element = document.getElementById(id);
        if (!element) return;
        element.textContent = (value || '').toString().trim() || '-';
    }

    function fillViewModal(payload) {
        setText('viewContactMessageId', payload.id || '-');
        setText('viewContactMessageName', payload.full_name || '-');
        setText('viewContactMessageEmail', payload.email || '-');
        setText('viewContactMessagePhone', payload.phone || '-');
        setText('viewContactMessageTopic', payload.topic || '-');
        setText('viewContactMessageStatus', payload.status_label || payload.status || '-');
        setText('viewContactMessageCreated', payload.created_at_display || '-');
        setText('viewContactMessageUpdated', payload.updated_at_display || '-');
        setText('viewContactMessageIp', payload.ip_address || '-');
        setText('viewContactMessageUserAgent', payload.user_agent || '-');
        setText('viewContactMessageBody', payload.message || '-');
    }

    function updateResultCount(element, count) {
        if (!element) return;
        element.textContent = count + (count === 1 ? ' result' : ' results');
    }

    function filterRows(rows, searchInput, statusFilter, noResultsRow, resultCount) {
        var term = normalize(searchInput ? searchInput.value : '');
        var status = normalize(statusFilter ? statusFilter.value : '');
        var visible = 0;

        rows.forEach(function (row) {
            var rowSearch = normalize(row.dataset.search);
            var rowStatus = normalize(row.dataset.status);
            var matchesSearch = term === '' || rowSearch.indexOf(term) !== -1;
            var matchesStatus = status === '' || rowStatus === status;
            var show = matchesSearch && matchesStatus;

            row.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', visible !== 0);
        }
        updateResultCount(resultCount, visible);
    }

    document.querySelectorAll('.view-contact-message-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var payload = parseJSON(button.dataset.message, {});
            fillViewModal(payload);
        });
    });

    var rows = Array.from(document.querySelectorAll('.contact-message-row'));
    var searchInput = document.getElementById('contactMessagesSearchInput');
    var statusFilter = document.getElementById('contactMessagesStatusFilter');
    var clearButton = document.getElementById('clearContactMessagesSearch');
    var noResultsRow = document.getElementById('contactMessagesNoResultsRow');
    var resultCount = document.getElementById('contactMessagesResultCount');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            filterRows(rows, searchInput, statusFilter, noResultsRow, resultCount);
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            filterRows(rows, searchInput, statusFilter, noResultsRow, resultCount);
        });
    }

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            filterRows(rows, searchInput, statusFilter, noResultsRow, resultCount);
            if (searchInput) searchInput.focus();
        });
    }

    filterRows(rows, searchInput, statusFilter, noResultsRow, resultCount);
})();
