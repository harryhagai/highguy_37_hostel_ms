// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
        const target = this.getAttribute("href");
        if (target && target !== "#") {
            e.preventDefault();
            const section = document.querySelector(target);
            if (section) {
                section.scrollIntoView({ behavior: "smooth" });
            }
        }
    });
});

// Professional header behavior on scroll
const siteHeader = document.querySelector(".site-header");
if (siteHeader) {
    const syncHeaderState = () => {
        siteHeader.classList.toggle("is-scrolled", window.scrollY > 24);
    };
    syncHeaderState();
    window.addEventListener("scroll", syncHeaderState, { passive: true });
}

// Auto-close mobile navbar after selecting any header link/action.
const headerCollapse = document.getElementById("navbarNav");
if (headerCollapse && window.bootstrap && typeof window.bootstrap.Collapse === "function") {
    const collapseInstance = window.bootstrap.Collapse.getOrCreateInstance(headerCollapse, { toggle: false });
    const isMobileHeader = () => window.matchMedia("(max-width: 991.98px)").matches;

    headerCollapse.querySelectorAll("a[href]").forEach((link) => {
        link.addEventListener("click", () => {
            if (!isMobileHeader()) return;
            if (!headerCollapse.classList.contains("show")) return;
            collapseInstance.hide();
        });
    });
}

// Active state indicator for header nav links
const headerNavLinks = Array.from(document.querySelectorAll(".header-nav .nav-link[href^='#']"));
if (headerNavLinks.length > 0) {
    const sectionTargets = headerNavLinks
        .map((link) => document.querySelector(link.getAttribute("href")))
        .filter(Boolean);

    const setActiveLink = (hash) => {
        headerNavLinks.forEach((link) => {
            const isActive = link.getAttribute("href") === hash;
            link.classList.toggle("is-active", isActive);
            if (isActive) {
                link.setAttribute("aria-current", "page");
            } else {
                link.removeAttribute("aria-current");
            }
        });
    };

    if (sectionTargets.length > 0) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setActiveLink(`#${entry.target.id}`);
                    }
                });
            },
            {
                root: null,
                rootMargin: "-45% 0px -45% 0px",
                threshold: 0,
            }
        );

        sectionTargets.forEach((section) => observer.observe(section));
    } else {
        setActiveLink("#home");
    }
}

// Hero image slideshow (every 3 seconds by default)
let heroImage = document.querySelector(".hero-media-image[data-hero-images]");
if (heroImage) {
    let heroImages = [];
    try {
        heroImages = JSON.parse(heroImage.dataset.heroImages || "[]");
    } catch (error) {
        heroImages = [];
    }

    if (heroImages.length > 1) {
        const heroCard = heroImage.closest(".hero-media-card");
        let currentIndex = heroImages.indexOf(heroImage.getAttribute("src"));
        if (currentIndex < 0) currentIndex = 0;
        let isAnimating = false;

        const interval = Math.max(parseInt(heroImage.dataset.heroInterval || "3000", 10) || 3000, 1200);
        const transitionMs = 900;

        window.setInterval(() => {
            if (!heroCard || isAnimating) return;
            isAnimating = true;

            currentIndex = (currentIndex + 1) % heroImages.length;
            const nextSrc = heroImages[currentIndex];

            // Preload first to avoid showing alt/text flash during slide.
            const preloader = new Image();
            preloader.onload = () => {
                const nextImage = heroImage.cloneNode(false);
                nextImage.setAttribute("src", nextSrc);
                nextImage.classList.add("next-slide");
                heroCard.appendChild(nextImage);

                window.requestAnimationFrame(() => {
                    heroImage.classList.add("is-slide-out");
                    nextImage.classList.add("is-active");
                });

                window.setTimeout(() => {
                    heroImage.remove();
                    nextImage.classList.remove("next-slide", "is-active");
                    heroImage = nextImage;
                    isAnimating = false;
                }, transitionMs);
            };

            preloader.onerror = () => {
                isAnimating = false;
            };

            preloader.src = nextSrc;
        }, interval);
    }
}

// Public hostels quick filters
const publicHostelsGrid = document.getElementById("publicHostelsGrid");
if (publicHostelsGrid) {
    const hostelCards = Array.from(publicHostelsGrid.querySelectorAll(".public-hostel-card"));
    const searchInput = document.getElementById("publicHostelSearchInput");
    const locationFilter = document.getElementById("publicHostelLocationFilter");
    const priceFilter = document.getElementById("publicHostelPriceFilter");
    const typeFilter = document.getElementById("publicHostelTypeFilter");
    const clearButton = document.getElementById("publicHostelClearFilters");
    const resultCount = document.getElementById("publicHostelResultCount");
    const noResults = document.getElementById("publicHostelNoResults");
    const nav = document.getElementById("publicHostelsNav");
    const prevButton = document.getElementById("publicHostelPrev");
    const nextButton = document.getElementById("publicHostelNext");
    const dotsWrap = document.getElementById("publicHostelDots");

    const normalize = (value) => String(value || "").trim().toLowerCase();
    const getVisibleCards = () => hostelCards.filter((card) => !card.classList.contains("d-none"));
    const getCardScrollLeft = (card) => {
        const cardRect = card.getBoundingClientRect();
        const gridRect = publicHostelsGrid.getBoundingClientRect();
        return Math.max(0, (cardRect.left - gridRect.left) + publicHostelsGrid.scrollLeft);
    };

    let cardsPerPage = 1;
    let totalPages = 1;
    let renderedPages = 0;

    const resolveCardsPerPage = (visibleCards) => {
        if (visibleCards.length < 2) return 1;
        const first = visibleCards[0];
        const second = visibleCards[1];
        const step = Math.max(1, second.offsetLeft - first.offsetLeft);
        return Math.max(1, Math.round(publicHostelsGrid.clientWidth / step));
    };

    const resolveCurrentPage = (visibleCards) => {
        if (visibleCards.length === 0) return 0;
        const currentLeft = publicHostelsGrid.scrollLeft;
        let resolvedPage = 0;
        let smallestDiff = Number.POSITIVE_INFINITY;

        for (let page = 0; page < totalPages; page += 1) {
            const cardIndex = page * cardsPerPage;
            const targetCard = visibleCards[cardIndex];
            if (!targetCard) break;

            const targetLeft = getCardScrollLeft(targetCard);
            const diff = Math.abs(currentLeft - targetLeft);
            if (diff < smallestDiff) {
                smallestDiff = diff;
                resolvedPage = page;
            }
        }

        return resolvedPage;
    };

    const scrollToPage = (page) => {
        const visibleCards = getVisibleCards();
        if (visibleCards.length === 0) return;
        const clampedPage = Math.max(0, Math.min(totalPages - 1, page));
        const cardIndex = clampedPage * cardsPerPage;
        const targetCard = visibleCards[cardIndex];
        if (!targetCard) return;

        publicHostelsGrid.scrollTo({
            left: getCardScrollLeft(targetCard),
            behavior: "smooth",
        });
    };

    const renderDots = (currentPage) => {
        if (!dotsWrap) return;

        if (renderedPages !== totalPages) {
            dotsWrap.innerHTML = "";
            for (let page = 0; page < totalPages; page += 1) {
                const dot = document.createElement("button");
                dot.type = "button";
                dot.className = "public-hostels-dot";
                dot.setAttribute("aria-label", `Go to page ${page + 1}`);
                dot.addEventListener("click", () => scrollToPage(page));
                dotsWrap.appendChild(dot);
            }
            renderedPages = totalPages;
        }

        const dots = dotsWrap.querySelectorAll(".public-hostels-dot");
        dots.forEach((dot, index) => {
            dot.classList.toggle("is-active", index === currentPage);
        });
    };

    const syncCarouselControls = () => {
        const visibleCards = getVisibleCards();
        const shouldShowNav = visibleCards.length > cardsPerPage;
        const currentPage = resolveCurrentPage(visibleCards);

        if (nav) {
            nav.classList.toggle("d-none", !shouldShowNav);
        }
        if (prevButton) {
            prevButton.disabled = !shouldShowNav || currentPage <= 0;
        }
        if (nextButton) {
            nextButton.disabled = !shouldShowNav || currentPage >= (totalPages - 1);
        }
        renderDots(currentPage);
    };

    const refreshCarousel = (resetScroll) => {
        const visibleCards = getVisibleCards();
        cardsPerPage = resolveCardsPerPage(visibleCards);
        totalPages = Math.max(1, Math.ceil(visibleCards.length / cardsPerPage));

        if (resetScroll) {
            publicHostelsGrid.scrollLeft = 0;
        }

        syncCarouselControls();
    };

    const applyPublicHostelsFilters = () => {
        const query = normalize(searchInput ? searchInput.value : "");
        const selectedLocation = normalize(locationFilter ? locationFilter.value : "");
        const selectedType = normalize(typeFilter ? typeFilter.value : "");
        const selectedPriceRange = normalize(priceFilter ? priceFilter.value : "");

        let visibleCount = 0;
        let minPrice = null;
        let maxPrice = null;

        if (selectedPriceRange.includes("-")) {
            const [rawMin, rawMax] = selectedPriceRange.split("-");
            const parsedMin = Number.parseFloat(rawMin);
            const parsedMax = Number.parseFloat(rawMax);
            minPrice = Number.isFinite(parsedMin) ? parsedMin : null;
            maxPrice = Number.isFinite(parsedMax) ? parsedMax : null;
        }

        hostelCards.forEach((card) => {
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

        refreshCarousel(true);
    };

    [searchInput, locationFilter, priceFilter, typeFilter].forEach((field) => {
        if (!field) return;
        const eventName = field.tagName === "INPUT" ? "input" : "change";
        field.addEventListener(eventName, applyPublicHostelsFilters);
    });

    if (clearButton) {
        clearButton.addEventListener("click", () => {
            if (searchInput) searchInput.value = "";
            if (locationFilter) locationFilter.value = "";
            if (priceFilter) priceFilter.value = "";
            if (typeFilter) typeFilter.value = "";
            applyPublicHostelsFilters();
        });
    }

    if (prevButton) {
        prevButton.addEventListener("click", () => {
            scrollToPage(resolveCurrentPage(getVisibleCards()) - 1);
        });
    }

    if (nextButton) {
        nextButton.addEventListener("click", () => {
            scrollToPage(resolveCurrentPage(getVisibleCards()) + 1);
        });
    }

    publicHostelsGrid.addEventListener("scroll", () => {
        syncCarouselControls();
    }, { passive: true });

    window.addEventListener("resize", () => {
        refreshCarousel(false);
    });

    const hostelsDataNode = document.getElementById("publicHostelsData");
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

    publicHostelsGrid.addEventListener("click", (event) => {
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

    publicHostelsGrid.addEventListener("keydown", (event) => {
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

    applyPublicHostelsFilters();
}
