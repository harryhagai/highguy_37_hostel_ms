const catalogGrid = document.getElementById("catalogGrid");

if (catalogGrid) {
    const cards = Array.from(catalogGrid.querySelectorAll(".public-hostel-card, .catalog-card"));
    const searchInput = document.getElementById("catalogSearchInput");
    const locationFilter = document.getElementById("catalogLocationFilter");
    const priceFilter = document.getElementById("catalogPriceFilter");
    const typeFilter = document.getElementById("catalogTypeFilter");
    const clearButton = document.getElementById("catalogClearFilters");
    const resultCount = document.getElementById("catalogResultCount");
    const noResults = document.getElementById("catalogNoResults");

    const normalize = (value) => String(value || "").trim().toLowerCase();

    const applyFilters = () => {
        const query = normalize(searchInput ? searchInput.value : "");
        const selectedLocation = normalize(locationFilter ? locationFilter.value : "");
        const selectedType = normalize(typeFilter ? typeFilter.value : "");
        const selectedPriceRange = normalize(priceFilter ? priceFilter.value : "");

        let minPrice = null;
        let maxPrice = null;
        let visibleCount = 0;

        if (selectedPriceRange.includes("-")) {
            const [rawMin, rawMax] = selectedPriceRange.split("-");
            const parsedMin = Number.parseFloat(rawMin);
            const parsedMax = Number.parseFloat(rawMax);
            minPrice = Number.isFinite(parsedMin) ? parsedMin : null;
            maxPrice = Number.isFinite(parsedMax) ? parsedMax : null;
        }

        cards.forEach((card) => {
            const searchable = normalize(card.dataset.search);
            const location = normalize(card.dataset.location);
            const type = normalize(card.dataset.type);
            const cardPrice = Number.parseFloat(card.dataset.price || "");
            const hasPrice = Number.isFinite(cardPrice);

            const matchesQuery = query === "" || searchable.includes(query);
            const matchesLocation = selectedLocation === "" || location === selectedLocation;
            const matchesType = selectedType === "" || type === selectedType;
            const matchesPrice = selectedPriceRange === ""
                || (hasPrice
                    && (minPrice === null || cardPrice >= minPrice)
                    && (maxPrice === null || cardPrice <= maxPrice));

            const matches = matchesQuery && matchesLocation && matchesType && matchesPrice;
            card.classList.toggle("d-none", !matches);

            if (matches) {
                visibleCount += 1;
            }
        });

        if (resultCount) {
            resultCount.textContent = `${visibleCount} result${visibleCount === 1 ? "" : "s"}`;
        }

        if (noResults) {
            noResults.classList.toggle("d-none", visibleCount > 0);
        }
    };

    [searchInput, locationFilter, priceFilter, typeFilter].forEach((field) => {
        if (!field) return;
        const eventName = field.tagName === "INPUT" ? "input" : "change";
        field.addEventListener(eventName, applyFilters);
    });

    if (clearButton) {
        clearButton.addEventListener("click", () => {
            if (searchInput) searchInput.value = "";
            if (locationFilter) locationFilter.value = "";
            if (priceFilter) priceFilter.value = "";
            if (typeFilter) typeFilter.value = "";
            applyFilters();
        });
    }

    const hostelsDataNode = document.getElementById("catalogHostelsData");
    let hostelsById = new Map();

    if (hostelsDataNode) {
        try {
            const parsedData = JSON.parse(hostelsDataNode.textContent || "[]");
            if (Array.isArray(parsedData)) {
                hostelsById = new Map(parsedData.map((hostel) => [String(hostel.id), hostel]));
            }
        } catch (error) {
            hostelsById = new Map();
        }
    }

    const detailModalElement = document.getElementById("hostelDetailModal");
    const mapModalElement = document.getElementById("hostelMapModal");
    const hasBootstrapModal = window.bootstrap && typeof window.bootstrap.Modal === "function";
    const detailModalInstance = detailModalElement && hasBootstrapModal
        ? window.bootstrap.Modal.getOrCreateInstance(detailModalElement)
        : null;
    const mapModalInstance = mapModalElement && hasBootstrapModal
        ? window.bootstrap.Modal.getOrCreateInstance(mapModalElement)
        : null;

    const detailTitleElement = document.getElementById("hostelDetailTitle");
    const detailImageElement = document.getElementById("hostelDetailImage");
    const detailGenderElement = document.getElementById("hostelDetailGender");
    const detailRatingElement = document.getElementById("hostelDetailRating");
    const detailLocationElement = document.getElementById("hostelDetailLocation");
    const detailDescriptionElement = document.getElementById("hostelDetailDescription");
    const detailPriceElement = document.getElementById("hostelDetailPrice");
    const detailFreeRoomsElement = document.getElementById("hostelDetailFreeRooms");
    const detailFreeBedsElement = document.getElementById("hostelDetailFreeBeds");
    const detailTotalRoomsElement = document.getElementById("hostelDetailTotalRooms");
    const detailRoomsCountElement = document.getElementById("hostelDetailRoomsCount");
    const detailRoomsBodyElement = document.getElementById("hostelDetailRoomsBody");
    const detailMapButton = document.getElementById("hostelDetailMapBtn");
    const detailBookButton = document.getElementById("hostelDetailBookBtn");

    const mapTitleElement = document.getElementById("hostelMapTitle");
    const mapFrameElement = document.getElementById("hostelMapFrame");
    const mapExternalLinkElement = document.getElementById("hostelMapExternalLink");

    const escapeHtml = (value) => String(value ?? "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");

    const formatPriceValue = (value) => {
        const numeric = Number(value);
        if (!Number.isFinite(numeric)) {
            return "Price on request";
        }
        return `TSh ${numeric.toLocaleString()}`;
    };

    const setMapContent = (location, hostelName) => {
        const cleanLocation = String(location || "").trim();
        const cleanName = String(hostelName || "Hostel").trim();
        const querySource = cleanLocation || cleanName;
        const encodedQuery = encodeURIComponent(querySource);
        const embedUrl = `https://maps.google.com/maps?q=${encodedQuery}&output=embed`;
        const externalUrl = `https://www.google.com/maps/search/?api=1&query=${encodedQuery}`;

        if (mapTitleElement) {
            mapTitleElement.textContent = cleanLocation !== ""
                ? `${cleanName} - Location Map`
                : `${cleanName} - Map`;
        }
        if (mapFrameElement) {
            mapFrameElement.src = embedUrl;
        }
        if (mapExternalLinkElement) {
            mapExternalLinkElement.href = externalUrl;
        }
    };

    const openMapModal = (location, hostelName) => {
        setMapContent(location, hostelName);

        if (!mapModalInstance) {
            if (mapExternalLinkElement && mapExternalLinkElement.href) {
                window.open(mapExternalLinkElement.href, "_blank", "noopener,noreferrer");
            }
            return;
        }

        if (detailModalElement && detailModalElement.classList.contains("show") && detailModalInstance) {
            const showMapAfterDetail = () => {
                detailModalElement.removeEventListener("hidden.bs.modal", showMapAfterDetail);
                mapModalInstance.show();
            };
            detailModalElement.addEventListener("hidden.bs.modal", showMapAfterDetail);
            detailModalInstance.hide();
            return;
        }

        mapModalInstance.show();
    };

    const buildRoomsRows = (rooms) => {
        if (!Array.isArray(rooms) || rooms.length === 0) {
            return '<tr><td colspan="6" class="text-center text-muted py-3">No room details available.</td></tr>';
        }

        return rooms.map((room) => {
            const statusText = String(room.status_label || "Full");
            const statusClass = String(room.status || "").toLowerCase() === "available"
                ? "text-bg-success"
                : "text-bg-secondary";
            const roomLabel = escapeHtml(room.room_number || `Room ${room.id || ""}`);
            const roomType = escapeHtml(room.room_type || "Standard");
            const roomPrice = escapeHtml(formatPriceValue(room.price));
            const roomCapacity = Number.isFinite(Number(room.capacity)) ? Number(room.capacity) : 0;
            const roomFreeBeds = Number.isFinite(Number(room.free_beds)) ? Number(room.free_beds) : 0;

            return `<tr>
                <td>${roomLabel}</td>
                <td>${roomType}</td>
                <td>${roomPrice}</td>
                <td>${roomCapacity}</td>
                <td>${roomFreeBeds}</td>
                <td><span class="badge ${statusClass}">${escapeHtml(statusText)}</span></td>
            </tr>`;
        }).join("");
    };

    const openHostelDetail = (hostelId) => {
        const hostel = hostelsById.get(String(hostelId || ""));
        if (!hostel || !detailModalInstance) return;

        if (detailTitleElement) {
            detailTitleElement.textContent = `${hostel.name || "Hostel"} Details`;
        }
        if (detailImageElement) {
            detailImageElement.src = String(hostel.image_url || "assets/images/logo.png");
            detailImageElement.alt = `${hostel.name || "Hostel"} image`;
        }
        if (detailGenderElement) {
            detailGenderElement.textContent = hostel.gender_label || "All Genders";
        }
        if (detailRatingElement) {
            detailRatingElement.textContent = hostel.rating_label || "No ratings yet";
        }
        if (detailLocationElement) {
            detailLocationElement.textContent = hostel.location || "Location not available";
        }
        if (detailDescriptionElement) {
            detailDescriptionElement.textContent = hostel.description || "No description available.";
        }
        if (detailPriceElement) {
            detailPriceElement.textContent = hostel.starting_price !== null
                ? `${formatPriceValue(hostel.starting_price)} / room`
                : "Price on request";
        }
        if (detailFreeRoomsElement) {
            detailFreeRoomsElement.textContent = String(Number(hostel.free_rooms || 0));
        }
        if (detailFreeBedsElement) {
            detailFreeBedsElement.textContent = String(Number(hostel.free_beds || 0));
        }
        if (detailTotalRoomsElement) {
            detailTotalRoomsElement.textContent = String(Number(hostel.total_rooms || 0));
        }

        const roomList = Array.isArray(hostel.rooms) ? hostel.rooms : [];
        if (detailRoomsCountElement) {
            detailRoomsCountElement.textContent = `${roomList.length} room${roomList.length === 1 ? "" : "s"}`;
        }
        if (detailRoomsBodyElement) {
            detailRoomsBodyElement.innerHTML = buildRoomsRows(roomList);
        }
        if (detailMapButton) {
            detailMapButton.dataset.location = String(hostel.location || "");
            detailMapButton.dataset.hostelName = String(hostel.name || "Hostel");
        }
        if (detailBookButton) {
            const isFull = Number(hostel.free_rooms || 0) <= 0 || Number(hostel.free_beds || 0) <= 0;
            if (isFull) {
                detailBookButton.classList.remove("btn-hero-primary");
                detailBookButton.classList.add("btn-secondary", "disabled");
                detailBookButton.setAttribute("aria-disabled", "true");
                detailBookButton.removeAttribute("href");
                detailBookButton.innerHTML = '<i class="bi bi-x-circle me-1"></i>Full';
            } else {
                detailBookButton.classList.remove("btn-secondary", "disabled");
                detailBookButton.classList.add("btn-hero-primary");
                detailBookButton.setAttribute("href", "auth/login.php");
                detailBookButton.removeAttribute("aria-disabled");
                detailBookButton.innerHTML = '<i class="bi bi-calendar-plus me-1"></i>Book Bed';
            }
        }

        detailModalInstance.show();
    };

    if (detailMapButton) {
        detailMapButton.addEventListener("click", () => {
            const location = detailMapButton.dataset.location || "";
            const hostelName = detailMapButton.dataset.hostelName || "Hostel";
            openMapModal(location, hostelName);
        });
    }

    catalogGrid.addEventListener("click", (event) => {
        const mapTrigger = event.target.closest(".public-hostel-map-trigger");
        if (mapTrigger) {
            event.preventDefault();
            openMapModal(mapTrigger.dataset.location || "", mapTrigger.dataset.hostelName || "Hostel");
            return;
        }

        const detailTrigger = event.target.closest(".public-hostel-detail-trigger");
        if (detailTrigger) {
            event.preventDefault();
            openHostelDetail(detailTrigger.dataset.hostelId || "");
        }
    });

    catalogGrid.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        const imageTrigger = event.target.closest("img.public-hostel-detail-trigger");
        if (!imageTrigger) return;
        event.preventDefault();
        openHostelDetail(imageTrigger.dataset.hostelId || "");
    });

    if (mapModalElement && mapFrameElement) {
        mapModalElement.addEventListener("hidden.bs.modal", () => {
            mapFrameElement.src = "about:blank";
        });
    }

    applyFilters();
}
