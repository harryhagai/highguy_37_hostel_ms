(function () {
    function parseJSON(value, fallback) {
        try {
            return value ? JSON.parse(value) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function fillRoomEdit(room) {
        document.getElementById('editRoomId').value = room.id ?? '';
        document.getElementById('editRoomHostel').value = room.hostel_id ?? '';
        document.getElementById('editRoomNumber').value = room.room_number ?? '';
        document.getElementById('editRoomType').value = room.room_type ?? '';

        var capacity = document.getElementById('editRoomCapacity');
        if (capacity) capacity.value = room.capacity ?? '';

        var available = document.getElementById('editRoomAvailable');
        if (available) available.value = room.available ?? '';

        document.getElementById('editRoomPrice').value = room.price ?? '';
    }

    function fillRoomView(room) {
        document.getElementById('viewRoomId').textContent = room.id ?? '-';
        document.getElementById('viewRoomHostel').textContent = room.hostel_name ?? '-';
        document.getElementById('viewRoomNumber').textContent = room.room_number ?? '-';
        document.getElementById('viewRoomType').textContent = room.room_type ?? '-';

        var capacity = document.getElementById('viewRoomCapacity');
        if (capacity) capacity.textContent = room.capacity ?? '-';

        var available = document.getElementById('viewRoomAvailable');
        if (available) available.textContent = room.available ?? '-';

        document.getElementById('viewRoomPrice').textContent = room.price ?? '-';
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

    var config = document.getElementById('manageRoomsConfig');
    if (!config) return;

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
})();
