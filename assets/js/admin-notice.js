(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function fillNoticeEdit(notice) {
        document.getElementById('editNoticeId').value = notice.id ?? '';
        document.getElementById('editNoticeTitle').value = notice.title ?? '';
        document.getElementById('editNoticeContent').value = notice.content ?? '';
    }

    function fillNoticeView(notice) {
        document.getElementById('viewNoticeId').textContent = notice.id ?? '-';
        document.getElementById('viewNoticeTitle').textContent = notice.title ?? '-';
        document.getElementById('viewNoticeCreated').textContent = notice.created_at ?? '-';
        document.getElementById('viewNoticeContent').textContent = notice.content ?? '-';
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

    var config = document.getElementById('manageNoticeConfig');
    if (!config) return;

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
})();
