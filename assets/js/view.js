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
        logClient("warn", "view:context_fetch_failed", { code });
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
        logClient("warn", "view:paste_unavailable", { code });
        return;
    }

    pasteData = data;

    if (isLocked(data.lockUntil, contextData.serverTime || Math.floor(Date.now() / 1000))) {
        const readable = new Date(Number(data.lockUntil) * 1000).toISOString();
        displayStatus(t("js.view.timelocked", `Paste is time-locked until ${readable}`, { time: readable }));
        decryptForm.classList.add("d-none");
        logClient("info", "view:paste_timelocked", { code });
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
    outputEl.textContent = payload.content || "";
    renderAttachment(payload.attachment || null);
    contentCard.classList.remove("d-none");
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
            logClient("warn", "view:binding_mismatch", { code });
            return;
        }

        currentPassword = password;
        displayStatus(t("js.view.decrypt_success", "Decryption successful."));
        renderPaste(payload);
        logClient("info", "view:decrypt_success", { code, hasDiscussion: Boolean(pasteData.modes?.discussion) });

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
        logClient("warn", "view:decrypt_failed", { code });
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
                const row = document.createElement("div");
                row.className = "border rounded p-2 bg-body-tertiary";
                row.textContent = plaintext;
                discussionList.appendChild(row);
                discussionCursor = Math.max(discussionCursor, Number(message.id || 0));
            } catch (_error) {
            }
        }
    } catch (_error) {
    }
}

function startDiscussionPolling() {
    loadDiscussion();
    window.setInterval(loadDiscussion, 4000);
}

discussionForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const text = discussionInput.value.trim();
    if (!text || !discussionParams) {
        return;
    }

    const envelope = await encryptDiscussionMessage(text, discussionParams);
    const response = await fetch(`api/discussion.php?code=${encodeURIComponent(code)}`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ envelope }),
    });
    const data = await response.json();
    if (data?.ok) {
        discussionInput.value = "";
        await loadDiscussion();
    } else {
        logClient("warn", "view:discussion_post_failed", { code });
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

installGlobalClientErrorLogging("view");
fetchContext().then(loadPaste);
