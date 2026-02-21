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
            if (confirmMessage) {
                var accepted = window.confirm(confirmMessage);
                if (!accepted) {
                    event.preventDefault();
                    return;
                }
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
