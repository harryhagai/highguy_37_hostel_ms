document.addEventListener('DOMContentLoaded', function() {
            // Handle location link clicks (both in card and modal)
            document.body.addEventListener('click', function(e) {
                if (e.target.classList.contains('location-link')) {
                    e.preventDefault();
                    var location = e.target.getAttribute('data-location');
                    var mapFrame = document.getElementById('mapFrame');
                    mapFrame.src = "https://www.google.com/maps?q=" + encodeURIComponent(location) + "&output=embed";
                    var mapModal = new bootstrap.Modal(document.getElementById('mapModal'));
                    mapModal.show();
                }
            });

            // Populate hostel details modal
            var hostelModal = document.getElementById('hostelModal');
            hostelModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var hostel = JSON.parse(button.getAttribute('data-hostel'));
                document.getElementById('modalHostelImage').src = hostel.image;
                document.getElementById('modalHostelName').textContent = hostel.name;
                // Location as link to open map modal
                document.getElementById('modalHostelLocation').innerHTML =
                    '<i class="bi bi-geo-alt-fill"></i> ' +
                    '<a href="#" class="location-link" data-location="' +
                    hostel.location.replace(/"/g, '&quot;') + '">' +
                    hostel.location +
                    '</a>';
                document.getElementById('modalHostelDesc').textContent = hostel.description;
                document.getElementById('modalHostelGender').textContent = hostel.gender_label || 'All Genders';
                document.getElementById('modalTotalRooms').textContent = hostel.total_rooms;
                document.getElementById('modalFreeRooms').textContent = hostel.free_rooms;
                // Google Maps embed (small preview)
                document.getElementById('modalHostelMap').innerHTML =
                    '<iframe width="100%" height="100%" style="border:0;" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade" ' +
                    'src="https://www.google.com/maps?q=' +
                    encodeURIComponent(hostel.location) +
                    '&output=embed"></iframe>';
            });
        });
