(function () {
    function toLower(value) {
        return String(value || '').toLowerCase();
    }

    function parseApp(button) {
        try {
            return JSON.parse(button.dataset.app || '{}');
        } catch (error) {
            return {};
        }
    }

    function normalizeStatus(value) {
        var status = toLower(value).trim();
        if (status === 'approved') {
            status = 'confirmed';
        }
        if (['pending', 'confirmed', 'cancelled', 'completed'].indexOf(status) === -1) {
            status = 'pending';
        }
        return status;
    }

    function normalizePaymentStatus(value) {
        var status = toLower(value).trim();
        if (['pending', 'verified', 'rejected', 'not_submitted'].indexOf(status) === -1) {
            status = 'not_submitted';
        }
        return status;
    }

    function paymentLabel(status) {
        if (status === 'not_submitted') return 'Not Submitted';
        if (status === 'pending') return 'Submitted';
        return status.charAt(0).toUpperCase() + status.slice(1);
    }

    function syncViewActionButtons(app) {
        var approveForm = document.getElementById('viewModalApproveForm');
        var rejectForm = document.getElementById('viewModalRejectForm');
        var approveId = document.getElementById('viewModalApproveId');
        var rejectId = document.getElementById('viewModalRejectId');
        var approveBtn = document.getElementById('viewModalApproveBtn');
        if (!approveForm || !rejectForm || !approveId || !rejectId || !approveBtn) {
            return;
        }

        var appId = app && app.id ? String(app.id) : '';
        approveId.value = appId;
        rejectId.value = appId;

        var username = (app && app.username ? String(app.username) : 'user').trim() || 'user';
        approveForm.setAttribute('data-confirm', 'Approve application for ' + username + '?');
        rejectForm.setAttribute('data-confirm', 'Reject application for ' + username + '?');

        var isPending = normalizeStatus(app && app.status) === 'pending';
        var hasTxn = String(app && app.payment_transaction_id ? app.payment_transaction_id : '').trim() !== '';

        approveForm.classList.toggle('d-none', !isPending);
        rejectForm.classList.toggle('d-none', !isPending);

        approveBtn.disabled = isPending ? !hasTxn : true;
        approveBtn.title = hasTxn ? 'Approve booking' : 'Transaction ID is required before approval';
    }

    function fillView(app) {
        var status = normalizeStatus(app && app.status);
        var statusLabel = status.charAt(0).toUpperCase() + status.slice(1);
        document.getElementById('viewAppToken').textContent = app.application_token || '-';
        document.getElementById('viewAppUser').textContent = app.username || '-';
        document.getElementById('viewAppPhone').textContent = app.phone || '-';
        document.getElementById('viewAppHostel').textContent = app.hostel_name || '-';
        document.getElementById('viewAppRoom').textContent = app.room_number || '-';
        document.getElementById('viewAppBed').textContent = app.bed_number || '-';
        document.getElementById('viewAppStatus').textContent = statusLabel;
        document.getElementById('viewAppBookingDate').textContent = app.booking_date || '-';
        document.getElementById('viewAppCreated').textContent = app.created_at || '-';

        var paymentStatus = normalizePaymentStatus(app && app.payment_status);
        document.getElementById('viewAppPaymentTxn').textContent = app.payment_transaction_id || '-';
        document.getElementById('viewAppPaymentStatus').textContent = paymentLabel(paymentStatus);
        document.getElementById('viewAppPaymentSubmitted').textContent = app.payment_submitted_at_display || app.payment_submitted_at || '-';
        document.getElementById('viewAppPaymentVerified').textContent = app.payment_verified_at_display || app.payment_verified_at || '-';
        document.getElementById('viewAppPaymentSms').textContent = app.payment_sms_text || '-';

        syncViewActionButtons(app);
    }

    document.querySelectorAll('.view-app-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            fillView(parseApp(this));
        });
    });

    var rows = Array.prototype.slice.call(document.querySelectorAll('.application-row'));
    if (!rows.length) {
        return;
    }

    var searchInput = document.getElementById('applicationsSearchInput');
    var statusFilter = document.getElementById('applicationsStatusFilter');
    var paymentFilter = document.getElementById('applicationsPaymentFilter');
    var dateFilter = document.getElementById('applicationsDateFilter');
    var clearSearchBtn = document.getElementById('clearApplicationsSearch');
    var resultCount = document.getElementById('applicationsResultCount');
    var noResultsRow = document.getElementById('applicationsNoResultsRow');
    var loadedInfo = document.getElementById('applicationsLoadedInfo');
    var lazySentinel = document.getElementById('applicationsLazySentinel');
    var tableWrap = document.querySelector('.users-table-wrap');
    var loadingMore = false;
    var state = {
        batchSize: 20,
        visibleLimit: 20,
        filteredRows: rows.slice(),
    };

    function getVisibleRows() {
        return state.filteredRows.slice(0, state.visibleLimit);
    }

    function updateLoadedInfo(total, visible) {
        if (!loadedInfo) {
            return;
        }

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

    function inDateRange(createdDate, filterValue) {
        if (!filterValue) {
            return true;
        }
        if (!createdDate) {
            return false;
        }

        var created = new Date(createdDate + 'T00:00:00');
        if (Number.isNaN(created.getTime())) {
            return false;
        }

        var now = new Date();
        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var weekAgo = new Date(today);
        weekAgo.setDate(today.getDate() - 6);
        var monthAgo = new Date(today);
        monthAgo.setDate(today.getDate() - 29);

        if (filterValue === 'today') {
            return created.getTime() === today.getTime();
        }
        if (filterValue === 'week') {
            return created >= weekAgo && created <= today;
        }
        if (filterValue === 'month') {
            return created >= monthAgo && created <= today;
        }
        if (filterValue === 'older') {
            return created < monthAgo;
        }
        return true;
    }

    function renderRows() {
        rows.forEach(function (row) {
            row.classList.add('d-none');
        });

        var visibleRows = getVisibleRows();
        visibleRows.forEach(function (row) {
            row.classList.remove('d-none');
        });

        var total = state.filteredRows.length;
        if (resultCount) {
            resultCount.textContent = total + ' results';
        }

        if (noResultsRow) {
            noResultsRow.classList.toggle('d-none', total !== 0);
        }

        updateLoadedInfo(total, visibleRows.length);
    }

    function loadMoreApplications() {
        if (loadingMore) {
            return;
        }
        if (state.visibleLimit >= state.filteredRows.length) {
            return;
        }

        loadingMore = true;
        state.visibleLimit = Math.min(state.visibleLimit + state.batchSize, state.filteredRows.length);
        renderRows();

        window.requestAnimationFrame(function () {
            loadingMore = false;
        });
    }

    function applyFilters(resetVisible) {
        if (resetVisible) {
            state.visibleLimit = state.batchSize;
        }

        var query = toLower(searchInput ? searchInput.value : '').trim();
        var status = toLower(statusFilter ? statusFilter.value : '').trim();
        var payment = toLower(paymentFilter ? paymentFilter.value : '').trim();
        var dateRange = toLower(dateFilter ? dateFilter.value : '').trim();

        state.filteredRows = rows.filter(function (row) {
            var searchText = toLower(row.dataset.search || '');
            var rowStatus = toLower(row.dataset.status || '');
            var rowPayment = toLower(row.dataset.paymentStatus || '');
            var rowCreatedDate = String(row.dataset.createdDate || '');

            var searchMatch = query === '' || searchText.indexOf(query) !== -1;
            var statusMatch = status === '' || rowStatus === status;
            var paymentMatch = payment === '' || rowPayment === payment;
            var dateMatch = inDateRange(rowCreatedDate, dateRange);
            return searchMatch && statusMatch && paymentMatch && dateMatch;
        });

        if (state.visibleLimit <= 0) {
            state.visibleLimit = state.batchSize;
        }

        renderRows();
    }

    function setupLazyLoader() {
        if (!lazySentinel) {
            return;
        }

        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        loadMoreApplications();
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
                loadMoreApplications();
            }
        };

        window.addEventListener('scroll', scrollHandler, { passive: true });
        if (tableWrap) {
            tableWrap.addEventListener('scroll', scrollHandler, { passive: true });
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            applyFilters(true);
        });
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', function () {
            applyFilters(true);
        });
    }
    if (paymentFilter) {
        paymentFilter.addEventListener('change', function () {
            applyFilters(true);
        });
    }
    if (dateFilter) {
        dateFilter.addEventListener('change', function () {
            applyFilters(true);
        });
    }
    if (clearSearchBtn && searchInput) {
        clearSearchBtn.addEventListener('click', function () {
            searchInput.value = '';
            if (statusFilter) statusFilter.value = '';
            if (paymentFilter) paymentFilter.value = '';
            if (dateFilter) dateFilter.value = '';
            searchInput.focus();
            applyFilters(true);
        });
    }

    setupLazyLoader();
    applyFilters(true);
})();
