(function () {
    var content = document.getElementById('dashboardContent');
    if (!content) return;

    var currentController = null;

    function getPageFromUrl() {
        var params = new URLSearchParams(window.location.search);
        return params.get('page') || 'dashboard';
    }

    function setActiveMenu(page) {
        document.querySelectorAll('.sidebar a[data-spa-page]').forEach(function (link) {
            link.classList.toggle('active', link.dataset.spaPage === page);
        });
    }

    function executeScripts(scope) {
        var scripts = Array.from(scope.querySelectorAll('script'));
        scripts.forEach(function (oldScript) {
            var newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(function (attr) {
                newScript.setAttribute(attr.name, attr.value);
            });
            if (!newScript.src) {
                newScript.textContent = oldScript.textContent;
            }
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
    }

    function runPageHooks() {
        if (typeof window.initUserHostelsPage === 'function') {
            window.initUserHostelsPage(document);
        }
        if (typeof window.initBookRoomModal === 'function') {
            window.initBookRoomModal(document);
        }
        if (typeof window.initUserBookBedPage === 'function') {
            window.initUserBookBedPage(document);
        }
    }

    function getSkeletonMarkup(page) {
        if (page === 'dashboard') {
            return (
                '<div class="spa-skeleton">' +
                    '<div class="skeleton-card skeleton-hero">' +
                        '<div class="skeleton-line w-40"></div>' +
                        '<div class="skeleton-line w-70"></div>' +
                        '<div class="skeleton-line w-55"></div>' +
                    '</div>' +
                    '<div class="skeleton-stat-grid">' +
                        '<div class="skeleton-card skeleton-stat"></div>' +
                        '<div class="skeleton-card skeleton-stat"></div>' +
                        '<div class="skeleton-card skeleton-stat"></div>' +
                        '<div class="skeleton-card skeleton-stat"></div>' +
                    '</div>' +
                    '<div class="skeleton-grid-2">' +
                        '<div class="skeleton-card skeleton-chart"></div>' +
                        '<div class="skeleton-card skeleton-side"></div>' +
                    '</div>' +
                '</div>'
            );
        }

        return (
            '<div class="spa-skeleton">' +
                '<div class="skeleton-card">' +
                    '<div class="skeleton-line w-35"></div>' +
                    '<div class="skeleton-line w-60"></div>' +
                '</div>' +
                '<div class="skeleton-card skeleton-table">' +
                    '<div class="skeleton-line w-100"></div>' +
                    '<div class="skeleton-line w-95"></div>' +
                    '<div class="skeleton-line w-90"></div>' +
                    '<div class="skeleton-line w-85"></div>' +
                    '<div class="skeleton-line w-80"></div>' +
                '</div>' +
            '</div>'
        );
    }

    function loadPage(page, pushState) {
        if (!page) page = 'dashboard';

        if (currentController) {
            currentController.abort();
        }

        currentController = new AbortController();
        content.classList.add('is-loading');
        content.innerHTML = getSkeletonMarkup(page);

        var requestParams = new URLSearchParams();
        requestParams.set('spa', '1');
        requestParams.set('page', page);

        if (page === 'book_room' || page === 'book_bed') {
            var currentUrlParams = new URLSearchParams(window.location.search);
            var hostelId = currentUrlParams.get('hostel_id');
            if (hostelId) {
                requestParams.set('hostel_id', hostelId);
            }
        }

        fetch('user_dashboard_layout.php?' + requestParams.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            signal: currentController.signal
        })
            .then(function (response) {
                if (!response.ok) throw new Error('Failed to load page.');
                return response.text();
            })
            .then(function (html) {
                content.innerHTML = html;
                executeScripts(content);
                runPageHooks();
                setActiveMenu(page);
                content.dataset.currentPage = page;

                if (pushState) {
                    var url = new URL(window.location.href);
                    url.searchParams.set('page', page);
                    url.searchParams.delete('hostel_id');
                    window.history.pushState({ page: page }, '', url.toString());
                }
            })
            .catch(function (error) {
                if (error.name === 'AbortError') return;
                content.innerHTML = '<div class="dashboard-card"><div class="alert alert-danger mb-0">Unable to load section. Please try again.</div></div>';
            })
            .finally(function () {
                content.classList.remove('is-loading');
                currentController = null;
            });
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[data-spa-page]');
        if (!link) return;

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target && link.target !== '_self') return;

        var href = link.getAttribute('href') || '';
        var url;
        try {
            url = new URL(href, window.location.href);
        } catch (e) {
            return;
        }

        if (url.pathname !== window.location.pathname) {
            return;
        }

        var targetPage = link.dataset.spaPage || 'dashboard';
        event.preventDefault();

        if ((targetPage === 'book_room' || targetPage === 'book_bed') && url.searchParams.get('hostel_id')) {
            window.history.pushState({ page: targetPage }, '', url.toString());
            loadPage(targetPage, false);
            return;
        }

        if (content.dataset.currentPage === targetPage) {
            setActiveMenu(targetPage);
            return;
        }

        loadPage(targetPage, true);
    });

    window.addEventListener('popstate', function () {
        loadPage(getPageFromUrl(), false);
    });

    setActiveMenu(content.dataset.currentPage || getPageFromUrl());
    runPageHooks();
})();
