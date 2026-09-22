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

        if (input) {
            return input.closest(".form-group, .field");
        }

        // Some fields have no named input until they hold a value, such as an UploadField with
        // nothing attached, so fall back to the holder's id
        return form.querySelector(`[id$="_${fieldName}_Holder"]`);
    };

    const applyLinkType = (form, value, { clearOthers = false } = {}) => {
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
        show(fields.anchor, value === "internal" || value === "external");

        // Only clear when the member changes type. Syncing on load/re-render must
        // not wipe values that still belong to the saved record.
        if (!clearOthers) {
            return;
        }

        // Hidden fields are still posted. Clear destinations that do not belong to
        // the chosen type so a leftover PageID cannot wipe an external URL on save.
        if (value === "external" || value === "file") {
            clearNamedInputs(form, "PageID");
        }
        if (value === "internal" || value === "file") {
            clearNamedInputs(form, "Link");
        }
        if (value === "internal" || value === "external") {
            clearNamedInputs(form, "File");
            clearNamedInputs(form, "FileID");
        }
    };

    const clearNamedInputs = (form, fieldName) => {
        form.querySelectorAll(
            `[name="${fieldName}"], [name^="${fieldName}["], [name="${fieldName}ID"], [name^="${fieldName}ID["]`
        ).forEach((input) => {
            if (input.type === "checkbox" || input.type === "radio") {
                input.checked = false;
                return;
            }
            if (input.value !== "") {
                input.value = "";
                input.dispatchEvent(new Event("change", { bubbles: true }));
                input.dispatchEvent(new Event("input", { bubbles: true }));
            }
        });
    };

    /**
     * The link type a radio stands for.
     *
     * A React rendered OptionsetField gives every radio a value of 1 and carries the real option
     * in an "option-val--<value>" class, so read that first and only fall back to the value for
     * a server rendered field.
     */
    const linkTypeOf = (radio) => {
        const match = (radio.className || "").match(/(?:^|\s)option-val--(\S+)/);

        return match ? match[1] : radio.value;
    };

    const syncLinkTypes = (root) => {
        (root || document)
            .querySelectorAll('[name="LinkType"]:checked')
            .forEach((checked) => {
                const form = checked.closest("form") || document;
                applyLinkType(form, linkTypeOf(checked));
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
            applyLinkType(target.closest("form") || document, linkTypeOf(target), {
                clearOthers: true,
            });
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

    /**
     * Post the open link back with the menu, so it is still open once the form comes back.
     *
     * The tree keeps the open link in the address bar rather than in the form, so copy it across
     * as the form is sent. The CMS sends the form from a jQuery handler on the action's click,
     * which fires no native submit event, so catch the click as well as a submit from the Enter
     * key. Both are captured so this runs before the CMS reads the form.
     */
    const syncOpenMenuItem = (form) => {
        if (!form || !form.matches || !form.matches(".menu-admin")) {
            return;
        }

        const input = Array.from(form.elements).find(
            (element) => element.name === "MenuItemID"
        );

        if (input) {
            input.value =
                new window.URL(window.location.href).searchParams.get("MenuItemID") || "";
        }
    };

    document.addEventListener("submit", (event) => syncOpenMenuItem(event.target), true);

    document.addEventListener(
        "click",
        (event) => {
            const button = event.target && event.target.closest
                ? event.target.closest('button, input[type="submit"]')
                : null;

            if (button) {
                syncOpenMenuItem(button.form);
            }
        },
        true
    );

    /**
     * Deleting a menu takes its links with it, so make sure it was meant.
     */
    document.addEventListener(
        "click",
        (event) => {
            const button = event.target && event.target.closest
                ? event.target.closest('[name="action_delete"]')
                : null;

            if (!button || !button.closest(".menu-admin")) {
                return;
            }

            const message = button.getAttribute("data-confirm-message");

            if (!window.confirm(message || "Delete this menu and all of its links?")) {
                event.preventDefault();
                event.stopPropagation();
            }
        },
        true
    );

    document.addEventListener("DOMContentLoaded", () => {
        syncLinkTypes(document);

        // Forms arrive after the initial load, so pick them up as they appear
        const observer = new window.MutationObserver(() => syncLinkTypes(document));
        observer.observe(document.body, { childList: true, subtree: true });
    });
})();
