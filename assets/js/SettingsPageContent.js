document.addEventListener("DOMContentLoaded", function () {

    const removeButtons = document.querySelectorAll(".remove-font");

    removeButtons.forEach((button) => {
        button.addEventListener("click", function () {
            const fontToRemove = button.getAttribute("data-font");

            const form = document.createElement("form");
            form.method = "POST";
            form.action = window.location.href;

            const fontInput = document.createElement("input");
            fontInput.type = "hidden";
            fontInput.name = "font_to_remove";
            fontInput.value = fontToRemove;
            form.appendChild(fontInput);

            const nonceInput = document.createElement("input");
            nonceInput.type = "hidden";
            nonceInput.name = "remove_font_nonce";
            nonceInput.value = EASYICONSYMBOLS.remove_nonce;
            form.appendChild(nonceInput);

            document.body.appendChild(form);
            form.submit();
        });
    });

    const popup = document.getElementById("default-fonts-popup");
    if (popup) {
        popup.style.display = "flex";

        document.getElementById("close-popup").addEventListener("click", () => {
            popup.style.display = "none";
        });

        document
            .getElementById("download-default-fonts")
            .addEventListener("click", () => {
                fetch(EASYICONSYMBOLS.rest_url, {
                    method: "POST",
                    credentials: "same-origin",
                    headers: {
                        "X-WP-Nonce": EASYICONSYMBOLS.rest_nonce,
                        "Content-Type": "application/json",
                    },
                })
                    .then((response) => {
                        if (!response.ok)
                            throw new Error("Network response was not ok");
                        return response.json();
                    })
                    .then(() => {
                        alert(EASYICONSYMBOLS.success_message);
                        window.location.reload();
                    })
                    .catch((error) => {
                        alert(EASYICONSYMBOLS.error_message);
                        console.error(error);
                    });
            });
    }

    const iconItems = document.querySelectorAll(".eics-icon-item");

    iconItems.forEach((icon) => {
        icon.addEventListener("click", () => {
            const iconName = icon.getAttribute("data-icon-name");
            const fontName = icon.getAttribute("data-font-name");
            const shortcode = `[eics-icon icon="${fontName}__${iconName}"]`;

            copyToClipboard(shortcode)
                .then(() => showTooltip(icon, "Copied!"))
                .catch((err) => console.error("Failed to copy:", err));
        });
    });

    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        } else {
            // Fallback for HTTP or unsupported browsers
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-9999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand("copy");
            } catch (err) {
                console.error("Fallback copy failed:", err);
            }

            document.body.removeChild(textArea);
            return Promise.resolve();
        }
    }

    function showTooltip(element, message) {
        const tooltip = document.createElement("div");
        tooltip.textContent = message;
        tooltip.style.position = "absolute";
        tooltip.style.background = "#000";
        tooltip.style.color = "#fff";
        tooltip.style.padding = "4px 8px";
        tooltip.style.borderRadius = "3px";
        tooltip.style.fontSize = "12px";
        tooltip.style.zIndex = "9999";
        tooltip.style.pointerEvents = "none";

        document.body.appendChild(tooltip);

        const rect = element.getBoundingClientRect();
        tooltip.style.left = `${
            rect.left + window.scrollX + rect.width / 2 - 30
        }px`;
        tooltip.style.top = `${rect.top + window.scrollY - 30}px`;

        setTimeout(() => {
            tooltip.remove();
        }, 1200);
    }

    const searchInput = document.getElementById("eics-icon-search");

    if (searchInput) {
        searchInput.addEventListener("input", function () {
            const query = this.value.toLowerCase();

            iconItems.forEach(function (item) {
                const iconName = item.getAttribute("data-icon-name").toLowerCase();
                const fontName = item.getAttribute("data-font-name").toLowerCase();

                if (iconName.includes(query) || fontName.includes(query)) {
                    item.style.display = "block";
                } else {
                    item.style.display = "none";
                }
            });

            const fontSections = document.querySelectorAll(".eics-font-section");

            fontSections.forEach((section) => {
                const visibleIcons = section.querySelectorAll(
                    '.eics-icon-item:not([style*="display: none"])'
                );
                if (visibleIcons.length > 0) {
                    section.style.display = "block";
                } else {
                    section.style.display = "none";
                }
            });

            const alphaNavs = document.querySelectorAll(".eics-alpha-nav");
            const alphaHeader = document.querySelectorAll(".eics-alpha-header");
            if (query.length > 0) {
                alphaNavs.forEach((nav) => (nav.style.display = "none"));
                alphaHeader.forEach((a) => (a.style.display = "none"));
            } else {
                alphaNavs.forEach((nav) => (nav.style.display = ""));
                alphaHeader.forEach((a) => (a.style.display = ""));
            }

            const alphaGroups = document.querySelectorAll(".eics-alpha-group");
            alphaGroups.forEach((alphaGroup) => {
                const visibleIcons = alphaGroup.querySelectorAll(
                    '.eics-icon-item:not([style*="display: none"])'
                );
                if (visibleIcons.length > 0) {
                    alphaGroup.style.display = "flex";
                } else {
                    alphaGroup.style.display = "none";
                }
            });
        });
    }

    const offsetTop = 120;

    document.querySelectorAll(".eics-font-section").forEach((section) => {
        const nav = section.querySelector(".eics-alpha-nav");
        const links = nav.querySelectorAll(".eics-alpha-link");
        const letterHeaders = Array.from(
            section.querySelectorAll('h3[id^="alpha-"]')
        );

        function onScroll() {
            const scrollY = window.scrollY;

            let currentLetterId = null;

            for (let i = letterHeaders.length - 1; i >= 0; i--) {
                const header = letterHeaders[i];
                const headerTop =
                    header.getBoundingClientRect().top + window.scrollY;
                if (headerTop - offsetTop <= scrollY + 10) {
                    currentLetterId = header.id;
                    break;
                }
            }

            links.forEach((link) => {
                const href = link.getAttribute("href").substring(1);
                if (href === currentLetterId) {
                    link.classList.add("active");
                } else {
                    link.classList.remove("active");
                }
            });
        }

        window.addEventListener("scroll", onScroll);

        onScroll();
    });

    const buttons = document.querySelectorAll(".eics-font-jump-btn");

    buttons.forEach((button) => {
        button.addEventListener("click", () => {
            const font = button.getAttribute("data-font");
            const targetSection = document.querySelector(
                `.eics-font-section[data-font="${font}"]`
            );
            if (targetSection) {
                targetSection.scrollIntoView({ behavior: "smooth" });
            }
        });
    });

    const disableSubsettingCheckbox = document.getElementById(
        "eics-disable-dynamic-subsetting"
    );


    if (disableSubsettingCheckbox) {
        disableSubsettingCheckbox.addEventListener("change", () => {
            fetch(EASYICONSYMBOLS.ajax_url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    action: "eics_save_dynamic_subsetting",
                    nonce: EASYICONSYMBOLS.settings_nonce,
                    value: disableSubsettingCheckbox.checked ? "1" : "0",
                }),
            }).catch(() => {
                // rollback on failure
                disableSubsettingCheckbox.checked =
                    !disableSubsettingCheckbox.checked;
                alert("Failed to save setting");
            });
        });
    }
});
