/* =====================================================================
   MarketCap X — Material Design 3 runtime (M0 foundation)
   ---------------------------------------------------------------------
   ADDITIVE, vanilla JS (no jQuery dependency). Provides:
     - persisted light/dark theme toggle  (window.MaterialUI.toggleTheme)
     - press ripples for .md-* interactive surfaces
     - nav drawer open/close
   It only acts on opt-in [data-md-*] hooks and .md-* classes, so loading it
   changes nothing until a surface uses those hooks (later phases). The initial
   theme is applied by a tiny inline <head> script (see layout) to avoid a
   flash; this file keeps the cookie/localStorage in sync and wires controls.
   ===================================================================== */
(function () {
    "use strict";

    var STORAGE_KEY = "md-theme";
    var COOKIE_KEY = "md_theme";

    function readCookie(name) {
        var m = document.cookie.match("(?:^|; )" + name + "=([^;]*)");
        return m ? decodeURIComponent(m[1]) : null;
    }

    function persist(theme) {
        try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) {}
        // 1-year cookie so the server can render the correct initial theme.
        document.cookie = COOKIE_KEY + "=" + theme + ";path=/;max-age=31536000;samesite=lax";
    }

    function currentTheme() {
        return document.documentElement.getAttribute("data-md-theme") || "dark";
    }

    function setTheme(theme) {
        theme = theme === "dark" ? "dark" : "light";
        document.documentElement.setAttribute("data-md-theme", theme);
        persist(theme);
        document.querySelectorAll("[data-md-theme-toggle]").forEach(function (el) {
            el.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
        });
        document.dispatchEvent(new CustomEvent("md-theme-change", { detail: { theme: theme } }));
    }

    function toggleTheme() {
        setTheme(currentTheme() === "dark" ? "light" : "dark");
    }

    // ---- Press ripple -------------------------------------------------
    var RIPPLE_SELECTOR =
        ".md-button, .md-icon-button, .md-fab, .md-chip, .md-nav-item, .md-tab, .md-list__item--interactive, .md-state";

    function spawnRipple(host, x, y) {
        if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
        var rect = host.getBoundingClientRect();
        var size = Math.max(rect.width, rect.height) * 2;
        var ripple = document.createElement("span");
        ripple.className = "md-ripple";
        ripple.style.width = ripple.style.height = size + "px";
        ripple.style.left = (x - rect.left - size / 2) + "px";
        ripple.style.top = (y - rect.top - size / 2) + "px";
        host.appendChild(ripple);
        ripple.addEventListener("animationend", function () { ripple.remove(); });
        // Safety cleanup in case animationend is missed.
        setTimeout(function () { ripple.remove(); }, 700);
    }

    function onPointerDown(e) {
        var host = e.target.closest ? e.target.closest(RIPPLE_SELECTOR) : null;
        if (!host || host.disabled) return;
        var cs = getComputedStyle(host);
        if (cs.position === "static") host.style.position = "relative";
        if (cs.overflow === "visible") host.style.overflow = "hidden";
        spawnRipple(host, e.clientX, e.clientY);
    }

    // ---- Nav drawer ---------------------------------------------------
    function wireDrawers() {
        document.querySelectorAll("[data-md-drawer-toggle]").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var sel = btn.getAttribute("data-md-drawer-toggle");
                var drawer = sel ? document.querySelector(sel) : null;
                if (drawer) drawer.classList.toggle("md-nav-drawer--open");
                document.body.classList.toggle("md-drawer-open");
            });
        });
    }

    function wireToggles() {
        document.querySelectorAll("[data-md-theme-toggle]").forEach(function (btn) {
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                toggleTheme();
            });
            btn.setAttribute("aria-pressed", currentTheme() === "dark" ? "true" : "false");
        });
    }

    function init() {
        // Keep storage in sync with whatever the inline head script applied.
        persist(currentTheme());
        document.addEventListener("pointerdown", onPointerDown, { passive: true });
        wireToggles();
        wireDrawers();
    }

    // Public API for later phases (theme switch in nav, etc.).
    window.MaterialUI = {
        setTheme: setTheme,
        toggleTheme: toggleTheme,
        currentTheme: currentTheme,
        readCookie: readCookie
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
