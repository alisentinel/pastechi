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
            hasMessage: Boolean(event.message),
            file: event.filename || "",
            line: event.lineno || 0,
            col: event.colno || 0,
        });
    });

    window.addEventListener("unhandledrejection", (event) => {
        const reasonType = typeof event.reason;
        logClient("error", `${scope}:unhandled_rejection`, {
            reasonType,
        });
    });
}
