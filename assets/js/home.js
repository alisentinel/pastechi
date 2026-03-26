import { installGlobalClientErrorLogging, logClient } from "./logger.js?v=20260327b";

const findForm = document.getElementById("findForm");
const currentLang = window.__APP_LANG || "en";

function t(key, fallback) {
    return window.__I18N?.[key] || fallback;
}

findForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    const codeInput = document.getElementById("codeInput");
    const code = (codeInput?.value || "").trim();
    if (!/^[0-9]{6}$/.test(code)) {
        alert(t("js.home.invalid_code", "Enter a valid 6-digit code."));
        logClient("warn", "home:invalid_code_entered");
        return;
    }
    window.location.href = `${code}?lang=${encodeURIComponent(currentLang)}`;
});

document.getElementById("createBtn")?.addEventListener("click", () => {
    window.location.href = `create.php?lang=${encodeURIComponent(currentLang)}`;
});

installGlobalClientErrorLogging("home");
