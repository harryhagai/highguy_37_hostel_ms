(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function fillHostelEdit(hostel) {
        document.getElementById('editHostelId').value = hostel.id ?? '';
        document.getElementById('editHostelName').value = hostel.name ?? '';
        document.getElementById('editHostelLocation').value = hostel.location ?? '';
        document.getElementById('editHostelDescription').value = hostel.description ?? '';
        document.getElementById('editHostelExistingImage').value = hostel.hostel_image ?? '';

        var preview = document.getElementById('editHostelPreview');
        if (preview) {
            if (hostel.hostel_image) {
                preview.src = '../' + hostel.hostel_image;
                preview.style.display = 'inline-block';
            } else {
                preview.style.display = 'none';
            }
        }
    }

    function fillHostelView(hostel) {
        document.getElementById('viewHostelId').textContent = hostel.id ?? '-';
        document.getElementById('viewHostelName').textContent = hostel.name ?? '-';
        document.getElementById('viewHostelLocation').textContent = hostel.location ?? '-';
        document.getElementById('viewHostelDescription').textContent = hostel.description ?? '-';
        document.getElementById('viewHostelCreated').textContent = hostel.created_at ?? '-';

        var image = document.getElementById('viewHostelImage');
        if (image) {
            if (hostel.hostel_image) {
                image.src = '../' + hostel.hostel_image;
                image.style.display = 'inline-block';
            } else {
                image.style.display = 'none';
            }
        }
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

    var config = document.getElementById('manageHostelConfig');
    if (!config) return;

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
})();
