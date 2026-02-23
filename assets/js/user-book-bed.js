(function () {
    function formatTsh(value) {
        return 'TSh ' + Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function initUserBookBedPage(scope) {
        var modal = scope.getElementById('bookBedModal');
        if (!modal || modal.dataset.bound) {
            return;
        }

        var semesterSelect = scope.getElementById('bookBedSemester');
        modal.dataset.bound = '1';

        var setValue = function (id, value) {
            var input = scope.getElementById(id);
            if (!input) return;
            input.value = String(value || '');
        };

        var refreshSemesterTotals = function () {
            if (!semesterSelect) return;

            var selectedOption = semesterSelect.options[semesterSelect.selectedIndex];
            var startDate = selectedOption ? (selectedOption.getAttribute('data-start-date') || '') : '';
            var endDate = selectedOption ? (selectedOption.getAttribute('data-end-date') || '') : '';
            var months = Number(selectedOption ? (selectedOption.getAttribute('data-months') || 0) : 0);
            var monthlyPrice = Number(modal.dataset.monthlyPrice || 0);

            var period = (startDate && endDate) ? (startDate + ' to ' + endDate) : '-';
            var totalPrice = monthlyPrice * months;

            setValue('bookBedSemesterPeriod', period);
            setValue('bookBedMonths', months > 0 ? (months + ' months') : '-');
            setValue('bookBedTotalPrice', formatTsh(totalPrice));
        };

        if (semesterSelect) {
            semesterSelect.addEventListener('change', refreshSemesterTotals);
        }

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
            modal.dataset.monthlyPrice = String(price);
            setValue('bookBedPrice', formatTsh(price));

            refreshSemesterTotals();
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
