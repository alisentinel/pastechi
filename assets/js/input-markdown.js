function applyWrap(textarea, before, after = before, fallback = "text") {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const value = textarea.value || "";
    const selected = value.slice(start, end);
    const body = selected || fallback;
    const insert = `${before}${body}${after}`;
    textarea.value = value.slice(0, start) + insert + value.slice(end);

    const selStart = start + before.length;
    const selEnd = selStart + body.length;
    textarea.focus();
    textarea.setSelectionRange(selStart, selEnd);
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
}

function applyCodeBlock(textarea) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const value = textarea.value || "";
    const selected = value.slice(start, end) || "code";
    const insert = `\n\`\`\`\n${selected}\n\`\`\`\n`;
    textarea.value = value.slice(0, start) + insert + value.slice(end);

    const cursorStart = start + 5;
    const cursorEnd = cursorStart + selected.length;
    textarea.focus();
    textarea.setSelectionRange(cursorStart, cursorEnd);
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
}

function applyLink(textarea) {
    const start = textarea.selectionStart ?? 0;
    const end = textarea.selectionEnd ?? start;
    const value = textarea.value || "";
    const selected = value.slice(start, end) || "link text";
    const insert = `[${selected}](https://example.com)`;
    textarea.value = value.slice(0, start) + insert + value.slice(end);

    const urlStart = start + selected.length + 3;
    const urlEnd = urlStart + "https://example.com".length;
    textarea.focus();
    textarea.setSelectionRange(urlStart, urlEnd);
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
}

function applyMarkdownAction(textarea, action) {
    switch (action) {
        case "bold":
            applyWrap(textarea, "**");
            break;
        case "italic":
            applyWrap(textarea, "*");
            break;
        case "underline":
            applyWrap(textarea, "__");
            break;
        case "inline-code":
            applyWrap(textarea, "`");
            break;
        case "code-block":
            applyCodeBlock(textarea);
            break;
        case "link":
            applyLink(textarea);
            break;
        default:
            break;
    }
}

document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-md-action]");
    if (!button) {
        return;
    }

    const toolbar = button.closest("[data-md-toolbar]");
    const container = toolbar?.parentElement;
    const textarea = container?.querySelector(".md-input");
    if (!textarea) {
        return;
    }

    event.preventDefault();
    applyMarkdownAction(textarea, button.getAttribute("data-md-action") || "");
});

document.addEventListener("keydown", (event) => {
    if (!(event.ctrlKey || event.metaKey)) {
        return;
    }

    const textarea = event.target instanceof HTMLTextAreaElement ? event.target : null;
    if (!textarea || !textarea.classList.contains("md-input")) {
        return;
    }

    const key = event.key.toLowerCase();
    if (key === "b") {
        event.preventDefault();
        applyMarkdownAction(textarea, "bold");
    } else if (key === "i") {
        event.preventDefault();
        applyMarkdownAction(textarea, "italic");
    } else if (key === "u") {
        event.preventDefault();
        applyMarkdownAction(textarea, "underline");
    } else if (key === "k") {
        event.preventDefault();
        applyMarkdownAction(textarea, "link");
    }
});
