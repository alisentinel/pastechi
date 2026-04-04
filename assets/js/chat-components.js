export function hashString(input) {
    let hash = 0;
    const value = String(input || "");
    for (let i = 0; i < value.length; i += 1) {
        hash = ((hash << 5) - hash) + value.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash);
}

export function escapeHtml(value) {
    return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

export function createAvatarDataUri(seed, label) {
    const hash = hashString(seed || label || "viewer");
    const hue = hash % 360;
    const initials = escapeHtml(String(label || "U").split(" ").map((part) => part[0] || "").join("").slice(0, 2).toUpperCase() || "U");
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'><rect width='64' height='64' rx='20' fill='hsl(${hue},70%,44%)'/><text x='50%' y='56%' dominant-baseline='middle' text-anchor='middle' fill='white' font-size='24' font-family='Arial, sans-serif'>${initials}</text></svg>`;
    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

export function isLikelyCode(text) {
    const value = String(text || "");
    if (value.length < 18) {
        return false;
    }

    const hints = ["function ", "const ", "let ", "class ", "<?php", "SELECT ", "{", "}", "=>", ";"];
    const newlineCount = (value.match(/\n/g) || []).length;
    const matchedHints = hints.reduce((count, hint) => count + (value.includes(hint) ? 1 : 0), 0);
    return newlineCount >= 1 && matchedHints >= 2;
}

export function applyCodeHighlighting(scope) {
    if (!window.hljs || !scope) {
        return;
    }
    scope.querySelectorAll("pre code").forEach((codeNode) => {
        window.hljs.highlightElement(codeNode);
    });
}

export function renderBubbleBody(container, text) {
    if (isLikelyCode(text)) {
        const pre = document.createElement("pre");
        const code = document.createElement("code");
        code.textContent = text;
        pre.appendChild(code);
        container.appendChild(pre);
        applyCodeHighlighting(container);
        return;
    }

    const body = document.createElement("div");
    body.className = "chat-bubble-text";
    body.textContent = text;
    container.appendChild(body);
}

function copyIconSvg() {
    return "<svg viewBox='0 0 24 24' width='14' height='14' aria-hidden='true' focusable='false'><path fill='currentColor' d='M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H10V7h9v14z'/></svg>";
}

async function writeClipboardText(text) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === "function") {
        await navigator.clipboard.writeText(text);
        return;
    }

    const temporary = document.createElement("textarea");
    temporary.value = text;
    temporary.setAttribute("readonly", "readonly");
    temporary.style.position = "absolute";
    temporary.style.left = "-9999px";
    document.body.appendChild(temporary);
    temporary.select();
    document.execCommand("copy");
    temporary.remove();
}

export function attachCopyButton(bubble, text) {
    const value = String(text || "");
    if (!value) {
        return;
    }

    const button = document.createElement("button");
    button.type = "button";
    button.className = "copy-bubble-btn";
    button.setAttribute("aria-label", "Copy");
    button.title = "Copy";
    button.innerHTML = copyIconSvg();

    button.addEventListener("click", async () => {
        try {
            await writeClipboardText(value);
            const previous = button.innerHTML;
            button.innerHTML = "<span class='copy-mark'>✓</span>";
            window.setTimeout(() => {
                button.innerHTML = previous;
            }, 900);
        } catch (_error) {
        }
    });

    bubble.appendChild(button);
}

export function appendChatBubble(container, {
    itemClass = "chat-item",
    bubbleClass = "chat-bubble",
    authorText,
    avatarAlt,
    avatarSeed,
    avatarLabel,
    text,
    metaText = "",
    accentColor = "",
}) {
    const messageText = String(text || "").trim();
    if (!messageText || !container) {
        return;
    }

    const item = document.createElement("article");
    item.className = itemClass;

    const avatar = document.createElement("img");
    avatar.className = "chat-avatar";
    avatar.alt = String(avatarAlt || authorText || "User");
    avatar.src = createAvatarDataUri(String(avatarSeed || avatarAlt || authorText || "anon"), String(avatarLabel || avatarAlt || "U"));

    const bubble = document.createElement("div");
    bubble.className = bubbleClass;
    if (accentColor) {
        bubble.style.setProperty("--bubble-accent", accentColor);
    }

    const authorRow = document.createElement("div");
    authorRow.className = "chat-author";
    authorRow.textContent = String(authorText || "User");
    bubble.appendChild(authorRow);

    renderBubbleBody(bubble, messageText);
    attachCopyButton(bubble, messageText);

    if (metaText) {
        const meta = document.createElement("div");
        meta.className = "chat-meta";
        meta.textContent = String(metaText);
        bubble.appendChild(meta);
    }

    item.appendChild(avatar);
    item.appendChild(bubble);
    container.appendChild(item);
}
