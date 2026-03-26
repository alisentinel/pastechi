import {
    buildShareUrl,
    encryptObject,
    fingerprintHash,
    generateTrackingCode,
    parseTimeLock,
    randomSalt,
    randomSecret,
} from "./crypto.js?v=20260327e";
import { installGlobalClientErrorLogging, logClient } from "./logger.js?v=20260327e";

window.__createModuleLoaded = true;

const form = document.getElementById("createForm");
const resultBox = document.getElementById("resultBox");
const shareLink = document.getElementById("shareLink");
const qrCodeEl = document.getElementById("qrCode");
const trackingCodeResultEl = document.getElementById("trackingCodeResult");
const createStatusEl = document.getElementById("createStatus");
const submitBtn = document.getElementById("submitBtn");

let contextData = { ipHash: "", serverTime: 0 };

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

async function createEncryptedPaste({
    payload,
    password,
    urlSecret,
    kdfIterations,
    ttlSeconds,
    maxViews,
    burnAfterRead,
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
        setStatus(`Encrypting and creating paste… attempt ${attempt}/${maxAttempts}`);

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
                burnAfterRead,
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

form?.addEventListener("submit", async (event) => {
    event.preventDefault();

    const content = document.getElementById("content").value;
    if (!content.trim()) {
        alert("Paste content is required.");
        return;
    }

    const title = document.getElementById("title").value || "";
    const password = document.getElementById("password").value || "";
    const ttlSeconds = Number(document.getElementById("ttlSeconds").value || "0");
    const maxViews = Number(document.getElementById("maxViews").value || "0");
    const burnAfterRead = document.getElementById("burnAfterRead").checked;
    const lockUntil = parseTimeLock(document.getElementById("timeLock").value || "");
    const bindingType = document.getElementById("bindingType").value;
    const discussion = document.getElementById("discussionMode").checked;
    const forensics = document.getElementById("forensicsMode").checked;
    const useFragmentKey = document.getElementById("useFragmentKey").checked;

    let bindingHash = "";
    if (bindingType === "ip") {
        if (!contextData.ipHash) {
            alert("IP binding unavailable right now.");
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
    };

    submitBtn.disabled = true;
    setStatus("Preparing encryption…");

    try {
        const result = await createEncryptedPaste({
            payload,
            password,
            urlSecret,
            kdfIterations,
            ttlSeconds,
            maxViews,
            burnAfterRead,
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
            alert(result?.data?.error || "Failed to create paste.");
            logClient("error", "create:api_create_failed", { error: result?.data?.error || "unknown" });
            setStatus("Failed to create paste.");
            submitBtn.disabled = false;
            return;
        }

        const url = buildShareUrl(result.code, urlSecret);
        shareLink.href = url;
        shareLink.textContent = url;
        trackingCodeResultEl.textContent = result.code;
        renderQrCode(url);
        resultBox.classList.remove("d-none");
        setStatus("Encryption complete. Paste is ready.");
        logClient("info", "create:paste_created", { discussion, forensics });
    } catch (error) {
        const message = error?.message || "Unexpected error while creating paste.";
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
