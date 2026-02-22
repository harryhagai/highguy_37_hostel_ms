(function () {
    var body = document.body;
    var toggleBtn = document.getElementById('sidebarToggle');
    var toggleIcon = toggleBtn ? toggleBtn.querySelector('i') : null;
    var backdrop = document.getElementById('sidebarBackdrop');

    if (!body || !toggleBtn) return;

    var mobileQuery = window.matchMedia('(max-width: 991.98px)');
    var storageKey = 'user_sidebar_collapsed';

    function syncToggleIcon() {
        if (!toggleIcon) return;

        if (mobileQuery.matches) {
            toggleIcon.className = body.classList.contains('sidebar-open')
                ? 'bi bi-chevron-left'
                : 'bi bi-chevron-right';
            return;
        }

        toggleIcon.className = body.classList.contains('sidebar-collapsed')
            ? 'bi bi-chevron-right'
            : 'bi bi-chevron-left';
    }

    function readCollapsedState() {
        try {
            return window.localStorage.getItem(storageKey) === '1';
        } catch (e) {
            return false;
        }
    }

    function writeCollapsedState(value) {
        try {
            window.localStorage.setItem(storageKey, value ? '1' : '0');
        } catch (e) {
            // ignore storage failures
        }
    }

    function closeMobileSidebar() {
        body.classList.remove('sidebar-open');
        syncToggleIcon();
    }

    function applyViewportState() {
        if (mobileQuery.matches) {
            body.classList.remove('sidebar-collapsed');
            closeMobileSidebar();
            syncToggleIcon();
            return;
        }

        body.classList.toggle('sidebar-collapsed', readCollapsedState());
        closeMobileSidebar();
        syncToggleIcon();
    }

    toggleBtn.addEventListener('click', function () {
        if (mobileQuery.matches) {
            body.classList.toggle('sidebar-open');
            syncToggleIcon();
            return;
        }

        var collapsed = !body.classList.contains('sidebar-collapsed');
        body.classList.toggle('sidebar-collapsed', collapsed);
        writeCollapsedState(collapsed);
        syncToggleIcon();
    });

    if (backdrop) {
        backdrop.addEventListener('click', closeMobileSidebar);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMobileSidebar();
        }
    });

    window.addEventListener('resize', applyViewportState);
    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', applyViewportState);
    }

    applyViewportState();
})();
