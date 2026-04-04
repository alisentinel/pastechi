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

function safeUrlOrEmpty(raw) {
    try {
        const parsed = new URL(String(raw || ""), window.location.origin);
        if (parsed.protocol === "http:" || parsed.protocol === "https:") {
            return parsed.toString();
        }
    } catch (_error) {
    }
    return "";
}

function appendInlineMarkdown(container, text) {
    const pattern = /(\[[^\]\n]+\]\((?:https?:\/\/)[^\s)]+\)|`[^`\n]+`|\*\*[^*\n]+\*\*|__[^_\n]+__|\*[^*\n]+\*)/g;
    let lastIndex = 0;
    let match = null;

    while ((match = pattern.exec(text)) !== null) {
        if (match.index > lastIndex) {
            container.appendChild(document.createTextNode(text.slice(lastIndex, match.index)));
        }

        const token = match[0];
        if (token.startsWith("[")) {
            const linkMatch = token.match(/^\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)$/);
            if (linkMatch) {
                const href = safeUrlOrEmpty(linkMatch[2]);
                if (href) {
                    const a = document.createElement("a");
                    a.href = href;
                    a.target = "_blank";
                    a.rel = "noopener noreferrer nofollow";
                    a.textContent = linkMatch[1];
                    container.appendChild(a);
                } else {
                    container.appendChild(document.createTextNode(token));
                }
            } else {
                container.appendChild(document.createTextNode(token));
            }
        } else if (token.startsWith("**") && token.endsWith("**")) {
            const el = document.createElement("strong");
            el.textContent = token.slice(2, -2);
            container.appendChild(el);
        } else if (token.startsWith("__") && token.endsWith("__")) {
            const el = document.createElement("u");
            el.textContent = token.slice(2, -2);
            container.appendChild(el);
        } else if (token.startsWith("`") && token.endsWith("`")) {
            const el = document.createElement("code");
            el.textContent = token.slice(1, -1);
            container.appendChild(el);
        } else if (token.startsWith("*") && token.endsWith("*")) {
            const el = document.createElement("em");
            el.textContent = token.slice(1, -1);
            container.appendChild(el);
        } else {
            container.appendChild(document.createTextNode(token));
        }

        lastIndex = pattern.lastIndex;
    }

    if (lastIndex < text.length) {
        container.appendChild(document.createTextNode(text.slice(lastIndex)));
    }
}

function renderStrictMarkdown(container, text) {
    const source = String(text || "");
    const blockPattern = /```([\s\S]*?)```/g;
    let lastIndex = 0;
    let match = null;

    while ((match = blockPattern.exec(source)) !== null) {
        const plainPart = source.slice(lastIndex, match.index);
        if (plainPart) {
            const lines = plainPart.split("\n");
            lines.forEach((line, index) => {
                if (line.length > 0) {
                    const body = document.createElement("span");
                    appendInlineMarkdown(body, line);
                    container.appendChild(body);
                }
                if (index < lines.length - 1) {
                    container.appendChild(document.createElement("br"));
                }
            });
        }

        const pre = document.createElement("pre");
        const code = document.createElement("code");
        code.textContent = String(match[1] || "").replace(/^\n+|\n+$/g, "");
        pre.appendChild(code);
        container.appendChild(pre);
        applyCodeHighlighting(pre);

        lastIndex = blockPattern.lastIndex;
    }

    const tail = source.slice(lastIndex);
    if (tail) {
        const lines = tail.split("\n");
        lines.forEach((line, index) => {
            if (line.length > 0) {
                const body = document.createElement("span");
                appendInlineMarkdown(body, line);
                container.appendChild(body);
            }
            if (index < lines.length - 1) {
                container.appendChild(document.createElement("br"));
            }
        });
    }
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
    if (isLikelyCode(text) && !String(text || "").includes("```")) {
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
    renderStrictMarkdown(body, String(text || ""));
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
