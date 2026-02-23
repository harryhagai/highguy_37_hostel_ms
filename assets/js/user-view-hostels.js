(function () {
    function initUserHostelsPage(scope) {
        var gridRoot = scope.getElementById('studentHostelsGrid');
        if (!gridRoot) return;
        if (gridRoot.dataset.bound === '1') return;
        gridRoot.dataset.bound = '1';

        var searchInput = scope.getElementById('userHostelsSearchInput');
        var clearSearchBtn = scope.getElementById('clearUserHostelsSearch');
        var locationFilter = scope.getElementById('userHostelsLocationFilter');
        var genderFilter = scope.getElementById('userHostelsGenderFilter');
        var availabilityFilter = scope.getElementById('userHostelsAvailabilityFilter');
        var cards = Array.from(scope.querySelectorAll('.student-hostel-card'));
        var resultCount = scope.getElementById('userHostelsResultCount');
        var noResults = scope.getElementById('userHostelsNoResults');

        function applyFilters() {
            if (!cards.length) {
                if (resultCount) resultCount.textContent = '0 results';
                if (noResults) noResults.classList.remove('d-none');
                return;
            }

            var query = (searchInput ? searchInput.value : '').trim().toLowerCase();
            var location = locationFilter ? locationFilter.value.trim().toLowerCase() : '';
            var gender = genderFilter ? genderFilter.value.trim().toLowerCase() : '';
            var availability = availabilityFilter ? availabilityFilter.value.trim().toLowerCase() : '';
            var visible = 0;

            cards.forEach(function (card) {
                var searchText = (card.dataset.search || '').toLowerCase();
                var cardLocation = (card.dataset.location || '').toLowerCase();
                var cardGender = (card.dataset.gender || '').toLowerCase();
                var cardAvailability = (card.dataset.availability || '').toLowerCase();

                var matches = true;
                if (query && searchText.indexOf(query) === -1) matches = false;
                if (location && cardLocation !== location) matches = false;
                if (gender && cardGender !== gender) matches = false;
                if (availability && cardAvailability !== availability) matches = false;

                card.classList.toggle('d-none', !matches);
                if (matches) visible++;
            });

            if (resultCount) {
                resultCount.textContent = visible + ' results';
            }
            if (noResults) {
                noResults.classList.toggle('d-none', visible > 0);
            }
        }

        [searchInput, locationFilter, genderFilter, availabilityFilter].forEach(function (el) {
            if (!el) return;
            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });

        if (clearSearchBtn && searchInput) {
            clearSearchBtn.addEventListener('click', function () {
                searchInput.value = '';
                searchInput.focus();
                applyFilters();
            });
        }

        applyFilters();

        var hostelModal = scope.getElementById('studentHostelModal');
        if (hostelModal && !hostelModal.dataset.bound) {
            hostelModal.dataset.bound = '1';
            var selectRoomBtn = scope.getElementById('studentModalSelectRoomBtn');

            if (selectRoomBtn && !selectRoomBtn.dataset.bound) {
                selectRoomBtn.dataset.bound = '1';
                selectRoomBtn.addEventListener('click', function () {
                    // Ensure modal/backdrop is fully cleared before SPA navigation.
                    var modalInstance = window.bootstrap && window.bootstrap.Modal
                        ? window.bootstrap.Modal.getInstance(hostelModal)
                        : null;
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    document.body.classList.remove('modal-open');
                    document.body.style.removeProperty('padding-right');
                    Array.from(document.querySelectorAll('.modal-backdrop')).forEach(function (el) {
                        if (el && el.parentNode) {
                            el.parentNode.removeChild(el);
                        }
                    });
                });
            }

            hostelModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) return;

                var raw = button.getAttribute('data-hostel') || '{}';
                var hostel;
                try {
                    hostel = JSON.parse(raw);
                } catch (e) {
                    hostel = {};
                }

                var image = scope.getElementById('studentModalHostelImage');
                var name = scope.getElementById('studentModalHostelName');
                var locationText = scope.getElementById('studentModalHostelLocation');
                var genderText = scope.getElementById('studentModalHostelGender');
                var desc = scope.getElementById('studentModalHostelDesc');
                var totalRooms = scope.getElementById('studentModalTotalRooms');
                var freeRooms = scope.getElementById('studentModalFreeRooms');
                var bedCapacity = scope.getElementById('studentModalBedCapacity');
                var priceSummary = scope.getElementById('studentModalRoomPriceSummary');
                var minPrice = scope.getElementById('studentModalMinPrice');
                var maxPrice = scope.getElementById('studentModalMaxPrice');
                var pricedRooms = scope.getElementById('studentModalPricedRooms');

                if (image) image.src = hostel.image || '../assets/images/logo.png';
                if (name) name.textContent = hostel.name || 'Hostel';
                if (locationText) locationText.textContent = hostel.location || '-';
                if (genderText) genderText.textContent = hostel.gender_label || 'All Genders';
                if (desc) desc.textContent = hostel.description || 'No description available.';
                if (totalRooms) totalRooms.textContent = hostel.total_rooms || 0;
                if (freeRooms) freeRooms.textContent = hostel.free_rooms || 0;
                if (bedCapacity) bedCapacity.textContent = hostel.bed_capacity || 0;
                if (priceSummary) priceSummary.textContent = hostel.room_price_summary || 'Price not set';
                if (minPrice) minPrice.textContent = hostel.room_price_min_label || '-';
                if (maxPrice) maxPrice.textContent = hostel.room_price_max_label || '-';
                if (pricedRooms) pricedRooms.textContent = hostel.priced_rooms || 0;

                if (selectRoomBtn) {
                    var hostelId = Number(hostel.id || 0);
                    if (hostelId > 0) {
                        selectRoomBtn.href = 'user_dashboard_layout.php?page=book_room&hostel_id=' + encodeURIComponent(hostelId);
                        selectRoomBtn.classList.remove('disabled');
                        selectRoomBtn.removeAttribute('aria-disabled');
                    } else {
                        selectRoomBtn.href = '#';
                        selectRoomBtn.classList.add('disabled');
                        selectRoomBtn.setAttribute('aria-disabled', 'true');
                    }
                }
            });
        }
    }

    window.initUserHostelsPage = initUserHostelsPage;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initUserHostelsPage(document);
        });
    } else {
        initUserHostelsPage(document);
    }
})();
