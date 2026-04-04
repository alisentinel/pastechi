import {
    b64UrlToBytes,
    decryptDiscussionMessage,
    decryptObject,
    encryptDiscussionMessage,
    fingerprintHash,
    parseUrlSecret,
} from "./crypto.js?v=20260327g";
import { installGlobalClientErrorLogging, logClient } from "./logger.js?v=20260327e";

const code = document.body.dataset.code || "";
const statusEl = document.getElementById("status");
const decryptForm = document.getElementById("decryptForm");
const passwordInput = document.getElementById("password");
const titleEl = document.getElementById("pasteTitle");
const outputEl = document.getElementById("pasteOutput");
const contentCard = document.getElementById("contentCard");
const attachmentBox = document.getElementById("attachmentBox");
const attachmentMetaEl = document.getElementById("attachmentMeta");
const downloadAttachmentBtn = document.getElementById("downloadAttachmentBtn");
const forensicCard = document.getElementById("forensicsCard");
const forensicOutput = document.getElementById("forensicsOutput");
const discussionCard = document.getElementById("discussionCard");
const discussionList = document.getElementById("discussionList");
const discussionForm = document.getElementById("discussionForm");
const discussionInput = document.getElementById("discussionInput");

let pasteData = null;
let contextData = { ipHash: "" };
let discussionCursor = 0;
let discussionParams = null;
let currentPassword = "";
let decryptedPayload = null;
let decryptedAttachment = null;
let discussionPollHandle = null;
const authorRoleMap = new Map();
let participantCounter = 0;

function t(key, fallback, replacements = {}) {
    let text = window.__I18N?.[key] || fallback;
    for (const [name, value] of Object.entries(replacements)) {
        text = text.replace(`{${name}}`, String(value));
    }
    return text;
}

function requiresFragment() {
    return Boolean(pasteData?.access?.requiresFragment ?? false);
}

function isPasswordProtected() {
    return Boolean(pasteData?.access?.passwordProtected ?? true);
}

function resolveUrlSecret() {
    const secret = parseUrlSecret();
    if (requiresFragment() && !secret) {
        return null;
    }
    return secret || "";
}

async function fetchContext() {
    try {
        const response = await fetch("api/context.php", { cache: "no-store" });
        const data = await response.json();
        if (data?.ok) {
            contextData = data;
        }
    } catch (_error) {
        contextData = { ipHash: "" };
        logClient("warn", "view:context_fetch_failed");
    }
}

function displayStatus(text) {
    statusEl.textContent = text;
}

function isLocked(lockUntil, serverTime) {
    return Number(lockUntil || 0) > Number(serverTime || 0);
}

async function loadPaste() {
    const response = await fetch(`api/get.php?code=${encodeURIComponent(code)}`, { cache: "no-store" });
    const data = await response.json();
    if (!data?.ok) {
        displayStatus(t("js.view.unavailable", "Paste unavailable or already destroyed."));
        decryptForm.classList.add("d-none");
        logClient("warn", "view:paste_unavailable");
        return;
    }

    pasteData = data;

    if (isLocked(data.lockUntil, contextData.serverTime || Math.floor(Date.now() / 1000))) {
        const readable = new Date(Number(data.lockUntil) * 1000).toISOString();
        displayStatus(t("js.view.timelocked", `Paste is time-locked until ${readable}`, { time: readable }));
        decryptForm.classList.add("d-none");
        logClient("info", "view:paste_timelocked");
        return;
    }

    const needPassword = isPasswordProtected();
    const secret = resolveUrlSecret();
    if (secret === null) {
        displayStatus(t("js.view.missing_fragment", "Missing key fragment in URL hash (#k=...)."));
        decryptForm.classList.add("d-none");
        return;
    }

    if (needPassword) {
        displayStatus(t("js.view.loaded_with_password", "Encrypted paste loaded. Enter password to decrypt."));
        decryptForm.classList.remove("d-none");
    } else {
        decryptForm.classList.add("d-none");
        displayStatus(t("js.view.loaded_auto", "Encrypted paste loaded. Decrypting…"));
    }

    if (data.modes?.forensics) {
        forensicCard.classList.remove("d-none");
        forensicOutput.textContent = JSON.stringify(data.forensics || {}, null, 2);
    }

    if (!needPassword) {
        await attemptDecrypt("");
    }
}

async function bindingHashByType(type) {
    if (type === "ip") {
        return contextData.ipHash || "";
    }
    if (type === "fingerprint") {
        return fingerprintHash();
    }
    return "";
}

async function verifyBinding(payload) {
    const expectedType = payload?.binding?.type || "none";
    const expectedHash = payload?.binding?.hash || "";
    if (expectedType === "none") {
        return true;
    }

    const actualHash = await bindingHashByType(expectedType);
    return expectedHash !== "" && expectedHash === actualHash;
}

function renderPaste(payload) {
    decryptedPayload = payload;
    titleEl.textContent = payload.title || t("paste.untitled", "Untitled");
    renderPasteThread(payload);
    renderAttachment(payload.attachment || null);
    contentCard.classList.remove("d-none");
}

function escapeHtml(value) {
    return String(value || "")
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/\"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

function hashString(input) {
    let hash = 0;
    const value = String(input || "");
    for (let i = 0; i < value.length; i += 1) {
        hash = ((hash << 5) - hash) + value.charCodeAt(i);
        hash |= 0;
    }
    return Math.abs(hash);
}

function formatRoleName(role) {
    if (role?.type === "author") {
        return "Paste Writer";
    }
    return `User ${Math.max(1, role.userIndex || 1)}`;
}

function createAvatarDataUri(seed, label) {
    const hash = hashString(seed || label || "viewer");
    const hue = hash % 360;
    const initials = escapeHtml(String(label || "U").split(" ").map((part) => part[0] || "").join("").slice(0, 2).toUpperCase() || "U");
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='64' height='64' viewBox='0 0 64 64'><rect width='64' height='64' rx='20' fill='hsl(${hue},70%,44%)'/><text x='50%' y='56%' dominant-baseline='middle' text-anchor='middle' fill='white' font-size='24' font-family='Arial, sans-serif'>${initials}</text></svg>`;
    return `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`;
}

function isLikelyCode(text) {
    const value = String(text || "");
    if (value.length < 18) {
        return false;
    }

    const hints = ["function ", "const ", "let ", "class ", "<?php", "SELECT ", "{" , "}", "=>", ";"];
    const newlineCount = (value.match(/\n/g) || []).length;
    const matchedHints = hints.reduce((count, hint) => count + (value.includes(hint) ? 1 : 0), 0);
    return newlineCount >= 1 && matchedHints >= 2;
}

function applyCodeHighlighting(scope) {
    if (!window.hljs || !scope) {
        return;
    }
    scope.querySelectorAll("pre code").forEach((codeNode) => {
        window.hljs.highlightElement(codeNode);
    });
}

function renderBubbleBody(container, text) {
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

function attachCopyButton(bubble, text) {
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

function renderPasteThread(payload) {
    outputEl.innerHTML = "";
    outputEl.className = "chat-thread";

    const messages = Array.isArray(payload?.messages) && payload.messages.length > 0
        ? payload.messages
        : [{ text: payload?.content || "" }];

    messages.forEach((message, index) => {
        const text = String(message?.text || "").trim();
        if (!text) {
            return;
        }

        const item = document.createElement("article");
        item.className = "chat-item chat-item--paste";

        const avatar = document.createElement("img");
        avatar.className = "chat-avatar";
        avatar.alt = "Paste";
        avatar.src = createAvatarDataUri(`paste:${index}:${code}`, "P");

        const bubble = document.createElement("div");
        bubble.className = "chat-bubble chat-bubble--paste";

        const name = document.createElement("div");
        name.className = "chat-author";
        name.textContent = `Paste ${index + 1}`;
        bubble.appendChild(name);

        renderBubbleBody(bubble, text);
        attachCopyButton(bubble, text);

        item.appendChild(avatar);
        item.appendChild(bubble);
        outputEl.appendChild(item);
    });
}

function getCookieValue(key) {
    const cookies = document.cookie ? document.cookie.split(";") : [];
    for (const item of cookies) {
        const [rawName, ...rest] = item.trim().split("=");
        if (decodeURIComponent(rawName) === key) {
            return decodeURIComponent(rest.join("="));
        }
    }
    return "";
}

function cookiePath() {
    const configured = typeof window.__APP_BASE === "string" ? window.__APP_BASE : "";
    if (!configured || configured === "/") {
        return "/";
    }
    return configured.startsWith("/") ? configured : `/${configured}`;
}

function setCookieValue(key, value, maxAgeSeconds) {
    const secure = window.location.protocol === "https:" ? "; Secure" : "";
    document.cookie = `${encodeURIComponent(key)}=${encodeURIComponent(value)}; Max-Age=${maxAgeSeconds}; Path=${cookiePath()}; SameSite=Lax${secure}`;
}

function getOrCreateParticipantKey() {
    const authorCookieKey = `pastechi_author_${code}`;
    const authorKey = getCookieValue(authorCookieKey);
    if (authorKey) {
        return authorKey;
    }

    const participantCookieKey = `pastechi_participant_${code}`;
    const existingParticipant = getCookieValue(participantCookieKey);
    if (existingParticipant) {
        return existingParticipant;
    }

    const generated = randomFallbackAuthorKey();
    setCookieValue(participantCookieKey, generated, 60 * 60 * 24 * 30);
    return generated;
}

function resolveRoleForAuthor(authorKey) {
    const key = authorKey || "anon";
    if (authorRoleMap.has(key)) {
        return authorRoleMap.get(key);
    }

    const pasteAuthorKey = String(pasteData?.discussion?.authorKey || "");
    let role = null;

    if (key && pasteAuthorKey && key === pasteAuthorKey) {
        role = { type: "author", userIndex: 0 };
    } else {
        participantCounter += 1;
        role = { type: "user", userIndex: participantCounter };
    }

    authorRoleMap.set(key, role);
    return role;
}

function bubbleColorByRole(role) {
    if (role.type === "author") {
        return "var(--chat-author-color)";
    }
    const hue = (role.userIndex * 71) % 360;
    return `hsl(${hue}, 72%, 48%)`;
}

function roleNameByRole(role) {
    return formatRoleName(role);
}

function renderDiscussionBubble(messageEnvelope, decodedMessage) {
    const messageText = String(decodedMessage?.text || "").trim();
    if (!messageText) {
        return;
    }

    const role = resolveRoleForAuthor(String(decodedMessage?.authorKey || ""));
    const roleName = roleNameByRole(role);
    const color = bubbleColorByRole(role);

    const item = document.createElement("article");
    item.className = "chat-item";

    const avatar = document.createElement("img");
    avatar.className = "chat-avatar";
    avatar.alt = roleName;
    avatar.src = createAvatarDataUri(`${decodedMessage?.authorKey || "anon"}:${roleName}`, roleName);

    const bubble = document.createElement("div");
    bubble.className = "chat-bubble";
    bubble.style.setProperty("--bubble-accent", color);

    const authorRow = document.createElement("div");
    authorRow.className = "chat-author";
    authorRow.textContent = roleName;
    bubble.appendChild(authorRow);

    renderBubbleBody(bubble, messageText);
    attachCopyButton(bubble, messageText);

    const meta = document.createElement("div");
    meta.className = "chat-meta";
    const ts = Number(messageEnvelope?.ts || 0);
    if (ts > 0) {
        meta.textContent = new Date(ts * 1000).toLocaleString();
    }
    bubble.appendChild(meta);

    item.appendChild(avatar);
    item.appendChild(bubble);
    discussionList.appendChild(item);
}

function formatBytes(size) {
    const bytes = Number(size || 0);
    if (!Number.isFinite(bytes) || bytes < 1024) {
        return `${Math.max(0, bytes)} B`;
    }
    if (bytes < (1024 * 1024)) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function safeFileName(name) {
    const trimmed = String(name || "attachment").trim();
    if (trimmed === "") {
        return "attachment";
    }
    return trimmed.replace(/[^a-zA-Z0-9._\- ()]/g, "_");
}

function renderAttachment(attachment) {
    decryptedAttachment = null;
    if (!attachmentBox || !downloadAttachmentBtn || !attachmentMetaEl) {
        return;
    }

    if (!attachment || typeof attachment !== "object" || !attachment.data) {
        attachmentBox.classList.add("d-none");
        return;
    }

    const name = safeFileName(attachment.name || "attachment");
    const type = String(attachment.type || "application/octet-stream");
    const size = Number(attachment.size || 0);
    attachmentMetaEl.textContent = `${name} · ${formatBytes(size)}`;
    decryptedAttachment = {
        name,
        type,
        bytes: b64UrlToBytes(String(attachment.data || "")),
    };
    attachmentBox.classList.remove("d-none");
}

async function attemptDecrypt(password) {
    if (!pasteData) {
        return;
    }

    const urlSecret = resolveUrlSecret();
    if (urlSecret === null) {
        return;
    }

    try {
        const payload = await decryptObject(pasteData.envelope, {
            code,
            password,
            urlSecret,
        });

        const bindingMatches = await verifyBinding(payload);
        if (!bindingMatches) {
            displayStatus(t("js.view.binding_mismatch", "This paste is bound to a different client context."));
            logClient("warn", "view:binding_mismatch");
            return;
        }

        currentPassword = password;
        displayStatus(t("js.view.decrypt_success", "Decryption successful."));
        renderPaste(payload);
        logClient("info", "view:decrypt_success", { hasDiscussion: Boolean(pasteData.modes?.discussion) });

        if (pasteData.modes?.discussion) {
            discussionCard.classList.remove("d-none");
            discussionParams = {
                code,
                password: currentPassword,
                urlSecret,
                discussionSalt: pasteData.discussion?.salt || "",
                kdfIterations: Number(pasteData.envelope?.kdfIterations || 260000),
            };
            startDiscussionPolling();
        }
    } catch (_error) {
        if (isPasswordProtected()) {
            displayStatus(t("js.view.decrypt_failed_password", "Unable to decrypt. Check password and link fragment."));
        } else {
            displayStatus(t("js.view.decrypt_failed_fragment", "Unable to decrypt. The link key may be missing or invalid."));
        }
        logClient("warn", "view:decrypt_failed");
    }
}

async function loadDiscussion() {
    if (!discussionParams) {
        return;
    }

    try {
        const response = await fetch(`api/discussion.php?code=${encodeURIComponent(code)}&since=${discussionCursor}`, { cache: "no-store" });
        const data = await response.json();
        if (!data?.ok || !Array.isArray(data.messages)) {
            return;
        }

        for (const message of data.messages) {
            try {
                const plaintext = await decryptDiscussionMessage(message, discussionParams);
                renderDiscussionBubble(message, plaintext);
                discussionCursor = Math.max(discussionCursor, Number(message.id || 0));
            } catch (_error) {
            }
        }
    } catch (_error) {
    }
}

function startDiscussionPolling() {
    if (discussionPollHandle) {
        window.clearInterval(discussionPollHandle);
    }
    loadDiscussion();
    discussionPollHandle = window.setInterval(loadDiscussion, 4000);
}

discussionForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const text = discussionInput.value.trim();
    if (!text || !discussionParams) {
        return;
    }

    await fetchContext();

    const envelope = await encryptDiscussionMessage({
        text,
        authorKey: getOrCreateParticipantKey(),
    }, discussionParams);
    const response = await fetch(`api/discussion.php?code=${encodeURIComponent(code)}`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            requestToken: String(contextData?.requestTokens?.discussionPost || ""),
            envelope,
        }),
    });
    const data = await response.json();
    if (data?.ok) {
        discussionInput.value = "";
        await loadDiscussion();
    } else if (data?.error === "invalid_request_token") {
        displayStatus(t("js.view.invalid_request_token", "Session token expired. Refresh the page and try again."));
    } else {
        logClient("warn", "view:discussion_post_failed");
    }
});

discussionInput?.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        discussionForm?.requestSubmit();
    }
});

decryptForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    await attemptDecrypt(passwordInput.value || "");
});

downloadAttachmentBtn?.addEventListener("click", () => {
    if (!decryptedAttachment) {
        return;
    }

    const blob = new Blob([decryptedAttachment.bytes], { type: decryptedAttachment.type || "application/octet-stream" });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = decryptedAttachment.name || "attachment";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
});

function randomFallbackAuthorKey() {
    const values = new Uint8Array(12);
    (window.crypto || window.msCrypto).getRandomValues(values);
    return Array.from(values).map((item) => item.toString(16).padStart(2, "0")).join("");
}

installGlobalClientErrorLogging("view");
fetchContext().then(loadPaste);
