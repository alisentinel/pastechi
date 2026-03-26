const encoder = new TextEncoder();
const decoder = new TextDecoder();

function getCryptoOrThrow() {
    const webCrypto = globalThis.crypto || globalThis.msCrypto;
    if (!webCrypto || typeof webCrypto.getRandomValues !== "function") {
        const origin = globalThis.location?.origin || "this origin";
        throw new Error(`Web Crypto API is unavailable at ${origin}. Use HTTPS or localhost in a modern browser.`);
    }
    return webCrypto;
}

function getSubtleOrThrow() {
    const cryptoObj = globalThis.crypto || globalThis.msCrypto;
    const subtle = cryptoObj?.subtle || cryptoObj?.webkitSubtle;
    if (!subtle) {
        const origin = globalThis.location?.origin || "this origin";
        const host = globalThis.location?.hostname || "";
        const localhostHint = host !== "localhost" && host !== "127.0.0.1"
            ? " Try opening the app via http://localhost/pastechi/"
            : "";
        throw new Error(`SubtleCrypto is unavailable at ${origin}. Use HTTPS or localhost in a modern browser.${localhostHint}`);
    }
    return subtle;
}

export function bytesToB64Url(bytes) {
    let binary = "";
    bytes.forEach((item) => {
        binary += String.fromCharCode(item);
    });
    return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/g, "");
}

export function b64UrlToBytes(value) {
    const pad = value.length % 4 === 0 ? "" : "=".repeat(4 - (value.length % 4));
    const base64 = value.replace(/-/g, "+").replace(/_/g, "/") + pad;
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i += 1) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

function randomBytes(length) {
    const bytes = new Uint8Array(length);
    getCryptoOrThrow().getRandomValues(bytes);
    return bytes;
}

function toUnixTimestamp(value) {
    if (!value) {
        return 0;
    }
    const stamp = Math.floor(new Date(value).getTime() / 1000);
    return Number.isFinite(stamp) && stamp > 0 ? stamp : 0;
}

export function randomSalt() {
    return bytesToB64Url(randomBytes(16));
}

export function randomSecret() {
    return bytesToB64Url(randomBytes(18));
}

export function generateTrackingCode() {
    return String(Math.floor(Math.random() * 1000000)).padStart(6, "0");
}

export async function sha256Hex(input) {
    const digest = await getSubtleOrThrow().digest("SHA-256", encoder.encode(input));
    return Array.from(new Uint8Array(digest)).map((item) => item.toString(16).padStart(2, "0")).join("");
}

export async function fingerprintHash() {
    const fingerprint = [
        navigator.userAgent,
        navigator.language,
        Intl.DateTimeFormat().resolvedOptions().timeZone || "unknown",
        String(screen.width),
        String(screen.height),
    ].join("|");
    return sha256Hex(fingerprint);
}

async function deriveAesKey({ code, password, urlSecret, salt, kdfIterations, purpose }) {
    const subtle = getSubtleOrThrow();
    const base = `${code}|${password || ""}|${urlSecret || ""}|${purpose}`;
    const keyMaterial = await subtle.importKey("raw", encoder.encode(base), "PBKDF2", false, ["deriveKey"]);
    return subtle.deriveKey(
        {
            name: "PBKDF2",
            salt: b64UrlToBytes(salt),
            iterations: kdfIterations,
            hash: "SHA-256",
        },
        keyMaterial,
        {
            name: "AES-GCM",
            length: 256,
        },
        false,
        ["encrypt", "decrypt"],
    );
}

export async function encryptObject(payload, { code, password, urlSecret, kdfIterations }) {
    const subtle = getSubtleOrThrow();
    const ivBytes = randomBytes(12);
    const salt = randomSalt();
    const key = await deriveAesKey({
        code,
        password,
        urlSecret,
        salt,
        kdfIterations,
        purpose: "paste",
    });

    const aad = encoder.encode(`pastechi:v1:${code}`);
    const plaintext = encoder.encode(JSON.stringify(payload));
    const ciphertext = await subtle.encrypt(
        {
            name: "AES-GCM",
            iv: ivBytes,
            additionalData: aad,
            tagLength: 128,
        },
        key,
        plaintext,
    );

    return {
        ciphertext: bytesToB64Url(new Uint8Array(ciphertext)),
        iv: bytesToB64Url(ivBytes),
        salt,
        kdfIterations,
        alg: "AES-GCM",
    };
}

export async function decryptObject(envelope, { code, password, urlSecret }) {
    const subtle = getSubtleOrThrow();
    const key = await deriveAesKey({
        code,
        password,
        urlSecret,
        salt: envelope.salt,
        kdfIterations: Number(envelope.kdfIterations),
        purpose: "paste",
    });

    const plaintext = await subtle.decrypt(
        {
            name: "AES-GCM",
            iv: b64UrlToBytes(envelope.iv),
            additionalData: encoder.encode(`pastechi:v1:${code}`),
            tagLength: 128,
        },
        key,
        b64UrlToBytes(envelope.ciphertext),
    );

    return JSON.parse(decoder.decode(plaintext));
}

async function deriveDiscussionKey({ code, password, urlSecret, discussionSalt, kdfIterations }) {
    return deriveAesKey({
        code,
        password,
        urlSecret,
        salt: discussionSalt,
        kdfIterations,
        purpose: "discussion",
    });
}

export async function encryptDiscussionMessage(message, params) {
    const subtle = getSubtleOrThrow();
    const key = await deriveDiscussionKey(params);
    const iv = randomBytes(12);
    const ciphertext = await subtle.encrypt(
        {
            name: "AES-GCM",
            iv,
            additionalData: encoder.encode(`pastechi:discussion:${params.code}`),
            tagLength: 128,
        },
        key,
        encoder.encode(message),
    );

    return {
        ciphertext: bytesToB64Url(new Uint8Array(ciphertext)),
        iv: bytesToB64Url(iv),
    };
}

export async function decryptDiscussionMessage(envelope, params) {
    const subtle = getSubtleOrThrow();
    const key = await deriveDiscussionKey(params);
    const plaintext = await subtle.decrypt(
        {
            name: "AES-GCM",
            iv: b64UrlToBytes(envelope.iv),
            additionalData: encoder.encode(`pastechi:discussion:${params.code}`),
            tagLength: 128,
        },
        key,
        b64UrlToBytes(envelope.ciphertext),
    );
    return decoder.decode(plaintext);
}

export function parseUrlSecret() {
    const hash = window.location.hash.startsWith("#") ? window.location.hash.slice(1) : window.location.hash;
    const params = new URLSearchParams(hash);
    return params.get("k") || "";
}

export function buildShareUrl(code, secret) {
    const configuredBase = typeof window !== "undefined" && typeof window.__APP_BASE === "string"
        ? window.__APP_BASE
        : "";
    const normalizedBase = configuredBase.endsWith("/") ? configuredBase.slice(0, -1) : configuredBase;
    const route = `${normalizedBase}/${code}`.replace(/\/+/g, "/");
    const url = new URL(route, window.location.origin);
    if (secret) {
        url.hash = `k=${encodeURIComponent(secret)}`;
    }
    return url.toString();
}

export function parseTimeLock(inputValue) {
    return toUnixTimestamp(inputValue);
}
