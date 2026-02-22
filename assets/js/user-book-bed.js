(function () {
    function initUserBookBedPage(scope) {
        var modal = scope.getElementById('bookBedModal');
        if (!modal || modal.dataset.bound) {
            return;
        }

        modal.dataset.bound = '1';
        modal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button) return;

            var raw = button.getAttribute('data-bed') || '{}';
            var bed;
            try {
                bed = JSON.parse(raw);
            } catch (e) {
                bed = {};
            }

            var setValue = function (id, value) {
                var input = scope.getElementById(id);
                if (!input) return;
                input.value = String(value || '');
            };

            setValue('bookBedId', bed.bed_id || '');
            setValue('bookBedHostel', bed.hostel_name || '');
            setValue('bookBedLocation', bed.hostel_location || '');

            var roomLabel = 'Room ' + String(bed.room_number || '-');
            if (String(bed.room_type || '').trim() !== '') {
                roomLabel += ' (' + String(bed.room_type) + ')';
            }
            setValue('bookBedRoom', roomLabel);
            setValue('bookBedNumber', bed.bed_number || '');

            var price = Number(bed.price || 0);
            setValue('bookBedPrice', 'TSh ' + price.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }));

            setValue('bookBedStartDate', bed.start_date || '');
            setValue('bookBedEndDate', bed.end_date || '');
        });
    }

    window.initUserBookBedPage = initUserBookBedPage;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initUserBookBedPage(document);
        });
    } else {
        initUserBookBedPage(document);
    }
})();
