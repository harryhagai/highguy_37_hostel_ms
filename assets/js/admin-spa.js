(function () {
    var content = document.getElementById('dashboardContent');
    if (!content) return;

    var currentController = null;

    function getPageFromUrl() {
        var params = new URLSearchParams(window.location.search);
        return params.get('page') || 'dashboard';
    }

    function getQueryParamsWithoutPage(source) {
        var params = source instanceof URLSearchParams
            ? new URLSearchParams(source.toString())
            : new URLSearchParams(source || window.location.search);
        params.delete('page');
        params.delete('spa');
        return params;
    }

    function resetTransientUi() {
        document.querySelectorAll('.modal.show').forEach(function (modalEl) {
            if (window.bootstrap && window.bootstrap.Modal) {
                var instance = window.bootstrap.Modal.getInstance(modalEl);
                if (instance) {
                    instance.hide();
                }
            }
            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.removeAttribute('aria-modal');
        });

        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });

        document.querySelectorAll('.tooltip').forEach(function (tooltip) {
            tooltip.remove();
        });

        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
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
        if (typeof window.initAdminDashboardChart === 'function') {
            window.initAdminDashboardChart();
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

    function loadPage(page, pushState, extraQuery) {
        if (!page) page = 'dashboard';

        if (currentController) {
            currentController.abort();
        }

        resetTransientUi();

        var pageParams = extraQuery instanceof URLSearchParams
            ? new URLSearchParams(extraQuery.toString())
            : new URLSearchParams(extraQuery || '');
        pageParams.set('page', page);

        var requestParams = new URLSearchParams(pageParams.toString());
        requestParams.set('spa', '1');

        currentController = new AbortController();
        content.classList.add('is-loading');
        content.innerHTML = getSkeletonMarkup(page);

        fetch('admin_dashboard_layout.php?' + requestParams.toString(), {
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
                content.dataset.currentQuery = getQueryParamsWithoutPage(pageParams).toString();

                if (pushState) {
                    var url = new URL(window.location.href);
                    url.search = pageParams.toString();
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

        event.preventDefault();
        var targetPage = link.dataset.spaPage || 'dashboard';
        var targetQuery = new URLSearchParams(link.dataset.spaQuery || '');
        loadPage(targetPage, true, targetQuery);
    });

    window.addEventListener('popstate', function () {
        loadPage(getPageFromUrl(), false, getQueryParamsWithoutPage(window.location.search));
    });

    content.dataset.currentQuery = getQueryParamsWithoutPage(window.location.search).toString();
    setActiveMenu(content.dataset.currentPage || getPageFromUrl());
})();
