export async function logClient(level, message, context = {}) {
    try {
        await fetch("api/log.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            keepalive: true,
            body: JSON.stringify({
                level,
                message,
                context,
            }),
        });
    } catch (_error) {
    }
}

export function installGlobalClientErrorLogging(scope) {
    window.addEventListener("error", (event) => {
        logClient("error", `${scope}:window_error`, {
            message: event.message || "unknown",
            file: event.filename || "",
            line: event.lineno || 0,
            col: event.colno || 0,
        });
    });

    window.addEventListener("unhandledrejection", (event) => {
        const reason = typeof event.reason === "string" ? event.reason : "promise_rejection";
        logClient("error", `${scope}:unhandled_rejection`, {
            reason,
        });
    });
}
