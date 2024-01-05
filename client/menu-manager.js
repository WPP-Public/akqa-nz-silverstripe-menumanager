(function () {
    const MenuManager = function (element) {
        const type = element.querySelectorAll("[name=LinkType]");

        const pageField = element.querySelector(
            "#Form_ItemEditForm_PageID_Holder"
        );
        const fileField = element.querySelector(
            "#Form_ItemEditForm_File_Holder"
        );
        const linkField = element.querySelector(
            "#Form_ItemEditForm_Link_Holder"
        );

        const anchorField = element.querySelector(
            "#Form_ItemEditForm_Anchor_Holder"
        );

        type.forEach((input) => {
            input.addEventListener("change", (event) => {
                const value = event.target.value;
                if (value == "internal") {
                    if (pageField) pageField.style.display = "flex";
                    if (linkField) linkField.style.display = "none";
                    if (fileField) fileField.style.display = "none";
                    if (anchorField) anchorField.style.display = "flex";
                } else if (value == "external") {
                    if (pageField) pageField.style.display = "none";
                    if (linkField) linkField.style.display = "flex";
                    if (fileField) fileField.style.display = "none";
                    if (anchorField) anchorField.style.display = "flex";
                } else if (value == "file") {
                    if (pageField) pageField.style.display = "none";
                    if (linkField) linkField.style.display = "none";
                    if (fileField) fileField.style.display = "flex";
                    if (anchorField) anchorField.style.display = "none";
                } else {
                    if (pageField) pageField.style.display = "none";
                    if (linkField) linkField.style.display = "none";
                    if (fileField) fileField.style.display = "none";
                    if (anchorField) anchorField.style.display = "none";
                }
            });
        });

        // set the initial state
        type.forEach((input) => {
            if (input.checked) {
                input.dispatchEvent(new Event("change"));
            }
        });
    };

    const startObserving = (domNode, classToLookFor, callback) => {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach(function (mutation) {
                const elementAdded = document.body.querySelector(
                    "." + classToLookFor + ":not(.menu-manager-loaded)"
                );

                if (elementAdded) {
                    elementAdded.classList.add("menu-manager-loaded");

                    callback(elementAdded);
                }
            });
        });

        observer.observe(domNode, {
            childList: true,
            attributes: true,
            characterData: true,
            subtree: true,
        });

        return observer;
    };

    // if menu-manager-tabset already exists in the markup, add the menu manager
    // functionality to it
    const menuManagerTabset = document.querySelector(".menu-manager-tabset");

    if (menuManagerTabset) {
        new MenuManager(menuManagerTabset);
    }

    startObserving(document.body, "menu-manager-tabset", (element) => {
        new MenuManager(element);
    });
})();
