import {
    b64UrlToBytes,
    decryptDiscussionMessage,
    decryptObject,
    encryptDiscussionMessage,
    fingerprintHash,
    parseUrlSecret,
} from "./crypto.js?v=20260327g";
import { installGlobalClientErrorLogging, logClient } from "./logger.js?v=20260327e";
import { appendChatBubble } from "./chat-components.js";

const code = document.body.dataset.code || "";
const statusEl = document.getElementById("status");
const newPasteBtn = document.getElementById("newPasteBtn");
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
const renderedMessageIds = new Set();

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
        newPasteBtn?.classList.remove("d-none");
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

function formatRoleName(role) {
    if (role?.type === "author") {
        return t("paste.role_writer", "Paste Writer");
    }
    return t("paste.role_user", "User {number}", {
        number: Math.max(1, role.userIndex || 1),
    });
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

        appendChatBubble(outputEl, {
            itemClass: "chat-item chat-item--paste",
            bubbleClass: "chat-bubble chat-bubble--paste",
            authorText: `Paste ${index + 1}`,
            avatarAlt: "Paste",
            avatarSeed: `paste:${index}:${code}`,
            avatarLabel: "P",
            text,
        });
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

    const ts = Number(messageEnvelope?.ts || 0);
    appendChatBubble(discussionList, {
        authorText: roleName,
        avatarAlt: roleName,
        avatarSeed: `${decodedMessage?.authorKey || "anon"}:${roleName}`,
        avatarLabel: roleName,
        text: messageText,
        metaText: ts > 0 ? new Date(ts * 1000).toLocaleString() : "",
        accentColor: color,
    });
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
                const messageId = Number(message.id || 0);
                if (renderedMessageIds.has(messageId)) {
                    continue;
                }
                const plaintext = await decryptDiscussionMessage(message, discussionParams);
                renderDiscussionBubble(message, plaintext);
                discussionCursor = Math.max(discussionCursor, messageId);
                renderedMessageIds.add(messageId);
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
    discussionCursor = 0;
    renderedMessageIds.clear();
    if (discussionList) {
        discussionList.innerHTML = "";
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
