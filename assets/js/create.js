import {
    buildShareUrl,
    bytesToB64Url,
    encryptObject,
    fingerprintHash,
    generateTrackingCode,
    parseTimeLock,
    randomSalt,
    randomSecret,
} from "./crypto.js?v=20260327g";
import { installGlobalClientErrorLogging, logClient } from "./logger.js?v=20260327e";

window.__createModuleLoaded = true;

const form = document.getElementById("createForm");
const createPane = document.getElementById("createPane");
const resultBox = document.getElementById("resultBox");
const shareLink = document.getElementById("shareLink");
const qrCodeEl = document.getElementById("qrCode");
const trackingCodeResultEl = document.getElementById("trackingCodeResult");
const createStatusEl = document.getElementById("createStatus");
const submitBtn = document.getElementById("submitBtn");
const createAnotherBtn = document.getElementById("createAnotherBtn");
const attachmentInput = document.getElementById("attachment");
const attachmentPolicyHint = document.getElementById("attachmentPolicyHint");
const currentLang = window.__APP_LANG || "en";
const attachmentPolicy = window.__ATTACHMENT_POLICY || {
    maxBytes: 0,
    allowedExtensions: ["*"],
};

let contextData = { ipHash: "", serverTime: 0 };

function t(key, fallback, replacements = {}) {
    let text = window.__I18N?.[key] || fallback;
    for (const [name, value] of Object.entries(replacements)) {
        text = text.replace(`{${name}}`, String(value));
    }
    return text;
}

async function fetchContext() {
    try {
        const response = await fetch("api/context.php", { cache: "no-store" });
        const data = await response.json();
        if (data?.ok) {
            contextData = data;
        }
    } catch (_error) {
        contextData = { ipHash: "", serverTime: 0 };
        logClient("warn", "create:context_fetch_failed");
    }
}

function setStatus(text) {
    createStatusEl.textContent = text;
}

function renderQrCode(url) {
    qrCodeEl.innerHTML = "";
    if (!window.QRCode) {
        return;
    }
    new window.QRCode(qrCodeEl, {
        text: url,
        width: 200,
        height: 200,
        correctLevel: window.QRCode.CorrectLevel.M,
    });
}

function showCreateForm() {
    createPane?.classList.remove("d-none");
    resultBox?.classList.add("d-none");
}

function showCreateResult() {
    createPane?.classList.add("d-none");
    resultBox?.classList.remove("d-none");
}

async function createEncryptedPaste({
    payload,
    attachmentMeta,
    password,
    urlSecret,
    kdfIterations,
    ttlSeconds,
    maxViews,
    uniqueViewsOnly,
    lockUntil,
    bindingType,
    bindingHash,
    discussion,
    forensics,
    access,
}) {
    const maxAttempts = 8;
    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
        const code = generateTrackingCode();
        setStatus(t("js.create.encrypt_attempt", `Encrypting and creating paste… attempt ${attempt}/${maxAttempts}`, {
            attempt,
            maxAttempts,
        }));

        const envelope = await encryptObject(payload, {
            code,
            password,
            urlSecret,
            kdfIterations,
        });

        const response = await fetch("api/create.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                code,
                envelope,
                ttlSeconds,
                maxViews,
                uniqueViewsOnly,
                lockUntil,
                binding: {
                    type: bindingType,
                    hash: bindingHash,
                },
                modes: {
                    discussion,
                    forensics,
                },
                access,
                discussionSalt: discussion ? randomSalt() : "",
                attachmentMeta,
            }),
        });

        let data = null;
        try {
            data = await response.json();
        } catch (_error) {
            data = { ok: false, error: "invalid_server_response" };
        }

        if (data?.ok) {
            return { ok: true, code, data };
        }

        if (data?.error !== "code_unavailable") {
            return { ok: false, code: "", data };
        }
    }

    return { ok: false, code: "", data: { ok: false, error: "code_unavailable" } };
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

function getFileExtension(name) {
    const value = String(name || "");
    const idx = value.lastIndexOf(".");
    if (idx < 0 || idx === value.length - 1) {
        return "";
    }
    return value.slice(idx + 1).toLowerCase();
}

function isExtensionAllowed(extension) {
    const allowed = Array.isArray(attachmentPolicy.allowedExtensions) ? attachmentPolicy.allowedExtensions : ["*"];
    if (allowed.includes("*")) {
        return true;
    }
    return allowed.includes(extension.toLowerCase());
}

function getSelectedAttachmentFile() {
    return attachmentInput?.files && attachmentInput.files.length > 0 ? attachmentInput.files[0] : null;
}

function validateAttachment(file) {
    if (!file) {
        return null;
    }

    const maxBytes = Number(attachmentPolicy.maxBytes || 0);
    if (maxBytes <= 0) {
        throw new Error(t("js.create.attachment_disabled", "Attachments are disabled by server policy."));
    }

    if (file.size > maxBytes) {
        throw new Error(t("js.create.attachment_too_large", "Attachment is too large. Maximum allowed is {size}.", {
            size: formatBytes(maxBytes),
        }));
    }

    const extension = getFileExtension(file.name);
    if (!isExtensionAllowed(extension)) {
        throw new Error(t("js.create.attachment_extension_not_allowed", "Attachment extension is not allowed."));
    }

    return {
        name: String(file.name || "attachment"),
        type: String(file.type || "application/octet-stream"),
        size: Number(file.size || 0),
        extension,
    };
}

async function buildEncryptedAttachmentPayload(file, meta) {
    const buffer = await file.arrayBuffer();
    return {
        name: meta.name,
        type: meta.type,
        size: meta.size,
        data: bytesToB64Url(new Uint8Array(buffer)),
        encoding: "base64url",
    };
}

function renderAttachmentPolicyHint() {
    if (!attachmentPolicyHint) {
        return;
    }

    const maxBytes = Number(attachmentPolicy.maxBytes || 0);
    const allowed = Array.isArray(attachmentPolicy.allowedExtensions) ? attachmentPolicy.allowedExtensions : ["*"];
    const allowedText = allowed.includes("*") ? "*" : allowed.join(", ");
    attachmentPolicyHint.textContent = t(
        "create.attachment_hint_dynamic",
        "Maximum size: {size}. Allowed extensions: {extensions}",
        {
            size: maxBytes > 0 ? formatBytes(maxBytes) : "0 B",
            extensions: allowedText,
        },
    );
}

form?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const content = document.getElementById("content").value;
    if (!content.trim()) {
        alert(t("js.create.content_required", "Paste content is required."));
        return;
    }

    const title = document.getElementById("title").value || "";
    const password = document.getElementById("password").value || "";
    const ttlSeconds = Number(document.getElementById("ttlSeconds").value || "0");
    const maxViews = Number(document.getElementById("maxViews").value || "0");
    const uniqueViewsOnly = document.getElementById("uniqueViewsOnly").checked;
    const lockUntil = parseTimeLock(document.getElementById("timeLock").value || "");
    const bindingType = document.getElementById("bindingType").value;
    const discussion = document.getElementById("discussionMode").checked;
    const forensics = document.getElementById("forensicsMode").checked;
    const useFragmentKey = document.getElementById("useFragmentKey").checked;
    const attachmentFile = getSelectedAttachmentFile();

    let attachmentMeta = null;
    let attachmentPayload = null;
    if (attachmentFile) {
        try {
            attachmentMeta = validateAttachment(attachmentFile);
            attachmentPayload = await buildEncryptedAttachmentPayload(attachmentFile, attachmentMeta);
        } catch (attachmentError) {
            alert(attachmentError?.message || t("js.create.attachment_invalid", "Invalid attachment."));
            return;
        }
    }

    let bindingHash = "";
    if (bindingType === "ip") {
        if (!contextData.ipHash) {
            alert(t("js.create.ip_unavailable", "IP binding unavailable right now."));
            return;
        }
        bindingHash = contextData.ipHash;
    }
    if (bindingType === "fingerprint") {
        bindingHash = await fingerprintHash();
    }

    const urlSecret = useFragmentKey ? randomSecret() : "";
    const kdfIterations = 260000;

    const payload = {
        title,
        content,
        binding: {
            type: bindingType,
            hash: bindingHash,
        },
        createdAt: new Date().toISOString(),
        attachment: attachmentPayload,
    };

    submitBtn.disabled = true;
    setStatus(t("js.create.preparing", "Preparing encryption…"));

    try {
        const result = await createEncryptedPaste({
            payload,
            attachmentMeta,
            password,
            urlSecret,
            kdfIterations,
            ttlSeconds,
            maxViews,
            uniqueViewsOnly,
            lockUntil,
            bindingType,
            bindingHash,
            discussion,
            forensics,
            access: {
                requiresFragment: useFragmentKey,
                passwordProtected: password.trim() !== "",
            },
        });

        if (!result.ok) {
            alert(result?.data?.error || t("js.create.failed_generic", "Failed to create paste."));
            logClient("error", "create:api_create_failed", { error: result?.data?.error || "unknown" });
            setStatus(t("js.create.failed_create", "Failed to create paste."));
            submitBtn.disabled = false;
            return;
        }

        const url = buildShareUrl(result.code, urlSecret);
        const localizedUrl = new URL(url);
        localizedUrl.searchParams.set("lang", currentLang);
        const localizedShareUrl = localizedUrl.toString();
        shareLink.href = localizedShareUrl;
        shareLink.textContent = localizedShareUrl;
        trackingCodeResultEl.textContent = result.code;
        renderQrCode(localizedShareUrl);
        showCreateResult();
        setStatus(t("js.create.encryption_complete", "Encryption complete. Paste is ready."));
        logClient("info", "create:paste_created", { discussion, forensics });
    } catch (error) {
        const message = error?.message || t("js.create.unexpected_error", "Unexpected error while creating paste.");
        alert(message);
        console.error(error);
        logClient("error", "create:unexpected_error", {
            message,
        });
        setStatus(message);
    }

    submitBtn.disabled = false;
});

installGlobalClientErrorLogging("create");
fetchContext();
renderAttachmentPolicyHint();

createAnotherBtn?.addEventListener("click", () => {
    form?.reset();
    setStatus("");
    submitBtn.disabled = false;
    shareLink.href = "#";
    shareLink.textContent = "";
    trackingCodeResultEl.textContent = "";
    qrCodeEl.innerHTML = "";
    showCreateForm();
    document.getElementById("content")?.focus();
});
