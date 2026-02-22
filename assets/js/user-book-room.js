(function () {
    function initBookRoomModal(scope) {
        var bookRoomModal = scope.getElementById('bookRoomModal');
        if (!bookRoomModal || bookRoomModal.dataset.bound) {
            return;
        }

        bookRoomModal.dataset.bound = '1';
        bookRoomModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var roomId = button.getAttribute('data-room-id') || '';
            var roomNumber = button.getAttribute('data-room-number') || '';
            var roomPrice = button.getAttribute('data-room-price') || '';
            var bookingMode = button.getAttribute('data-booking-mode') || 'room';
            var availableBedsRaw = button.getAttribute('data-available-beds') || '[]';

            var roomIdInput = scope.getElementById('modalRoomId');
            var roomNumberInput = scope.getElementById('modalRoomNumber');
            var roomPriceInput = scope.getElementById('modalRoomPrice');
            var bedSelectWrap = scope.getElementById('modalBedSelectWrap');
            var bedSelect = scope.getElementById('modalBedId');
            var submitBtn = bookRoomModal.querySelector('button[type="submit"]');

            if (roomIdInput) roomIdInput.value = roomId;
            if (roomNumberInput) roomNumberInput.value = roomNumber;
            if (roomPriceInput) roomPriceInput.value = 'TZS ' + roomPrice;

            var availableBeds = [];
            try {
                availableBeds = JSON.parse(availableBedsRaw);
            } catch (e) {
                availableBeds = [];
            }

            if (bedSelect) {
                bedSelect.innerHTML = '<option value="">Select bed</option>';
                availableBeds.forEach(function (bed) {
                    var option = document.createElement('option');
                    option.value = String(bed.id || '');
                    option.textContent = String(bed.label || ('Bed ' + (bed.bed_number || '')));
                    bedSelect.appendChild(option);
                });
            }

            var isBedMode = bookingMode === 'bed';
            if (bedSelectWrap) {
                bedSelectWrap.classList.toggle('d-none', !isBedMode);
            }

            if (bedSelect) {
                bedSelect.required = isBedMode;
                bedSelect.disabled = !isBedMode || availableBeds.length === 0;
                if (isBedMode && availableBeds.length === 0) {
                    bedSelect.innerHTML = '<option value="">No bed available</option>';
                }
            }

            if (submitBtn) {
                submitBtn.disabled = isBedMode && availableBeds.length === 0;
            }
        });
    }

    window.initBookRoomModal = initBookRoomModal;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initBookRoomModal(document);
        });
    } else {
        initBookRoomModal(document);
    }
})();
