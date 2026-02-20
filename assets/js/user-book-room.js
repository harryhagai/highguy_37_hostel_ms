const bookRoomModal = document.getElementById('bookRoomModal');
    if (bookRoomModal) {
        bookRoomModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            const roomId = button.getAttribute('data-room-id');
            const roomNumber = button.getAttribute('data-room-number');
            const roomPrice = button.getAttribute('data-room-price');

            document.getElementById('modalRoomId').value = roomId;
            document.getElementById('modalRoomNumber').value = roomNumber;
            document.getElementById('modalRoomPrice').value = "$" + roomPrice;
        });
    }
