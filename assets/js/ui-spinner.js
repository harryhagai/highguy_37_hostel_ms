(function () {
    function hasNoSpinner(el) {
        return el.dataset.noSpinner === "true" || el.classList.contains("no-spinner");
    }

    function shouldSkipLink(link) {
        var href = (link.getAttribute("href") || "").trim();
        if (!href || href === "#" || href.startsWith("#")) return true;
        if (href.startsWith("javascript:")) return true;
        if (href.startsWith("mailto:") || href.startsWith("tel:")) return true;
        if (link.hasAttribute("download")) return true;
        if ((link.getAttribute("target") || "").toLowerCase() === "_blank") return true;
        if (link.dataset.bsToggle) return true;
        return hasNoSpinner(link);
    }

    function shouldSkipButton(button) {
        if (button.disabled) return true;
        if (button.dataset.bsToggle) return true;
        if (button.classList.contains("btn-close")) return true;
        if (button.classList.contains("dropdown-toggle")) return true;
        return hasNoSpinner(button);
    }

    function setLoading(el) {
        if (!el || el.dataset.loading === "1") return;
        el.dataset.loading = "1";

        var spinner = document.createElement("span");
        spinner.className = "spinner-border spinner-border-sm me-2";
        spinner.setAttribute("role", "status");
        spinner.setAttribute("aria-hidden", "true");

        if (el.tagName === "A") {
            if (!el.dataset.originalHtml) el.dataset.originalHtml = el.innerHTML;
            el.classList.add("disabled");
            el.style.pointerEvents = "none";
            el.prepend(spinner);
            return;
        }

        if (el.tagName === "BUTTON" || el.tagName === "INPUT") {
            if (el.tagName === "BUTTON") {
                if (!el.dataset.originalHtml) el.dataset.originalHtml = el.innerHTML;
                el.innerHTML = "";
                el.appendChild(spinner);
                var loadingText = document.createTextNode("Loading...");
                el.appendChild(loadingText);
            } else {
                if (!el.dataset.originalValue) el.dataset.originalValue = el.value;
                el.value = "Loading...";
            }
            el.disabled = true;
        }
    }

    document.addEventListener(
        "submit",
        function (event) {
            if (event.defaultPrevented) return;

            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            var confirmMessage = form.getAttribute("data-confirm");
            var skipConfirm = form.dataset.swalConfirmAccepted === "1";
            if (confirmMessage && !skipConfirm) {
                event.preventDefault();
                var submitterForRetry = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');

                window.setTimeout(function () {
                    if (form.dataset.skipSwalConfirm === "1") {
                        delete form.dataset.skipSwalConfirm;
                        return;
                    }

                    var confirmAction = window.AdminAlerts && typeof window.AdminAlerts.confirmAction === "function"
                        ? window.AdminAlerts.confirmAction
                        : function (message) { return Promise.resolve(window.confirm(message)); };

                    confirmAction(confirmMessage, { form: form })
                        .then(function (accepted) {
                            if (!accepted) return;

                            form.dataset.swalConfirmAccepted = "1";
                            if (typeof form.requestSubmit === "function") {
                                if (submitterForRetry) {
                                    form.requestSubmit(submitterForRetry);
                                } else {
                                    form.requestSubmit();
                                }
                                return;
                            }

                            if (submitterForRetry && !shouldSkipButton(submitterForRetry)) {
                                setLoading(submitterForRetry);
                            }
                            form.submit();
                        })
                        .catch(function () {
                            // Ignore confirmation errors and keep form untouched.
                        });
                }, 0);
                return;
            }

            if (skipConfirm) {
                delete form.dataset.swalConfirmAccepted;
            }

            if (event.defaultPrevented) return;

            var submitter = event.submitter;
            if (!submitter) {
                submitter = form.querySelector('button[type="submit"], input[type="submit"]');
            }
            if (submitter && !shouldSkipButton(submitter)) {
                setLoading(submitter);
            }
        }
    );

    document.addEventListener(
        "click",
        function (event) {
            var link = event.target.closest("a");
            if (link && !shouldSkipLink(link)) {
                setLoading(link);
            }
        },
        true
    );
})();
