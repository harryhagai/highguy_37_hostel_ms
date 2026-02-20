(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function fillBedEdit(bed) {
        document.getElementById('editBedId').value = bed.id ?? '';
        document.getElementById('editBedRoom').value = bed.room_id ?? '';
        document.getElementById('editBedNumber').value = bed.bed_number ?? '';
        document.getElementById('editBedStatus').value = bed.status ?? 'active';
    }

    function fillBedView(bed) {
        document.getElementById('viewBedId').textContent = bed.id ?? '-';
        document.getElementById('viewBedHostel').textContent = bed.hostel_name ?? '-';
        document.getElementById('viewBedRoom').textContent = bed.room_number ?? '-';
        document.getElementById('viewBedNumber').textContent = bed.bed_number ?? '-';
        document.getElementById('viewBedStatus').textContent = bed.status ?? '-';
        document.getElementById('viewBedCreated').textContent = bed.created_at ?? '-';
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

    var config = document.getElementById('manageBedsConfig');
    if (!config) return;

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
})();
