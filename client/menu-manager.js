(function () {
    /**
     * Show only the fields that belong to the chosen link type.
     *
     * Holders are found relative to the form the radios live in, rather than by a fixed element
     * id, because the same fields are rendered under different form names depending on where a
     * menu item is being edited.
     */
    const holderFor = (form, fieldName) => {
        const input = form.querySelector(
            `[name="${fieldName}"], [name^="${fieldName}["]`
        );

        return input ? input.closest(".form-group, .field") : null;
    };

    const applyLinkType = (form, value) => {
        const fields = {
            page: holderFor(form, "PageID"),
            link: holderFor(form, "Link"),
            file: holderFor(form, "File"),
            anchor: holderFor(form, "Anchor"),
        };

        const show = (holder, visible) => {
            if (holder) {
                holder.style.display = visible ? "" : "none";
            }
        };

        show(fields.page, value === "internal");
        show(fields.link, value === "external");
        show(fields.file, value === "file");
        show(fields.anchor, value === "internal");
    };

    const syncLinkTypes = (root) => {
        (root || document)
            .querySelectorAll('[name="LinkType"]:checked')
            .forEach((checked) => {
                const form = checked.closest("form") || document;
                applyLinkType(form, checked.value);
            });
    };

    /**
     * Delegated, so it keeps working when the CMS replaces a panel or React re-renders a form.
     */
    document.addEventListener("change", (event) => {
        const target = event.target;

        if (!target || !target.matches) {
            return;
        }

        if (target.matches('[name="LinkType"]')) {
            applyLinkType(target.closest("form") || document, target.value);
            return;
        }

        if (!target.matches("select")) {
            return;
        }

        const selector = target.closest(".menu-admin__selector");

        if (!selector || !target.value) {
            return;
        }

        const source = target.closest("[data-menu-admin-link]");
        const base = source
            ? source.getAttribute("data-menu-admin-link")
            : window.location.pathname;

        // Resolved against the current page, so a relative base still lands in the right place
        const url = new window.URL(base, window.location.href);
        url.searchParams.set("MenuSetID", target.value);

        window.location.assign(url.toString());
    });

    document.addEventListener("DOMContentLoaded", () => {
        syncLinkTypes(document);

        // Forms arrive after the initial load, so pick them up as they appear
        const observer = new window.MutationObserver(() => syncLinkTypes(document));
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
