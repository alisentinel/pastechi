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
        toggle.textContent = theme === "light" ? nightLabel : dayLabel;
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
