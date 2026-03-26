import { installGlobalClientErrorLogging, logClient } from "./logger.js?v=20260327b";

const findForm = document.getElementById("findForm");

findForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    const codeInput = document.getElementById("codeInput");
    const code = (codeInput?.value || "").trim();
    if (!/^[0-9]{6}$/.test(code)) {
        alert("Enter a valid 6-digit code.");
        logClient("warn", "home:invalid_code_entered");
        return;
    }
    window.location.href = code;
});

document.getElementById("createBtn")?.addEventListener("click", () => {
    window.location.href = "create.php";
});

installGlobalClientErrorLogging("home");
