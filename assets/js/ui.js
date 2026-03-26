const root = document.documentElement;
const storageKey = "pastechi_theme";

function setTheme(mode) {
    const theme = mode === "light" ? "light" : "dark";
    root.setAttribute("data-bs-theme", theme);
    document.body.setAttribute("data-theme", theme);
    const toggle = document.getElementById("themeToggle");
    if (toggle) {
        toggle.setAttribute("aria-pressed", theme === "light" ? "true" : "false");
        const dayLabel = toggle.dataset.dayLabel || "Day";
        const nightLabel = toggle.dataset.nightLabel || "Night";
        const label = theme === "light" ? nightLabel : dayLabel;
        const icon = theme === "light" ? 
            '<svg class="icon-moon" fill="currentColor" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>' :
            '<svg class="icon-sun" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3" stroke="currentColor" stroke-width="2"/><line x1="12" y1="21" x2="12" y2="23" stroke="currentColor" stroke-width="2"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64" stroke="currentColor" stroke-width="2"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78" stroke="currentColor" stroke-width="2"/><line x1="1" y1="12" x2="3" y2="12" stroke="currentColor" stroke-width="2"/><line x1="21" y1="12" x2="23" y2="12" stroke="currentColor" stroke-width="2"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36" stroke="currentColor" stroke-width="2"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22" stroke="currentColor" stroke-width="2"/></svg>';
        toggle.innerHTML = icon + ' ' + label;
    }
}

function loadTheme() {
    const saved = localStorage.getItem(storageKey);
    if (saved === "light" || saved === "dark") {
        setTheme(saved);
        return;
    }

    const prefersLight = window.matchMedia && window.matchMedia("(prefers-color-scheme: light)").matches;
    setTheme(prefersLight ? "light" : "dark");
}

document.getElementById("themeToggle")?.addEventListener("click", () => {
    const current = root.getAttribute("data-bs-theme") || "dark";
    const next = current === "dark" ? "light" : "dark";
    localStorage.setItem(storageKey, next);
    setTheme(next);
});

loadTheme();
