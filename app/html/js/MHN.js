document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('[data-tooltip="sichtbarkeit"] .toggle-group .toggle-on').forEach(function(element) {
        element.setAttribute("title", "sichtbar für andere Mitglieder");
    });
    document.querySelectorAll('[data-tooltip="sichtbarkeit"] .toggle-group .toggle-off').forEach(function(element) {
        element.setAttribute("title", "nicht sichtbar");
    });
});

function formatTime(timestamp, includeDate = true, includeTime = true) {
    if (typeof timestamp !== "object") {
        timestamp = new Date(timestamp);
    }
    let options = {};
    if (includeDate) {
        options.year = options.month = options.day = "2-digit";
    }
    if (includeTime) {
        options.hour = options.minute = "2-digit";
    }
    return timestamp.toLocaleDateString("de-DE", options);
}

let csrfToken = "";
async function callApi(method = "GET", url = "", data = {}, loader = undefined) {
    if (loader) {
        loader.classList.remove("hidden");
    }

    try {
        if (data instanceof FormData && data.has("_csrfToken")) {
            data.delete("_csrfToken");
        } else if (data && data["_csrfToken"]) {
            delete data["_csrfToken"];
        }

        const options = {
            method,
            headers: {
                "X-CSRF-Token": csrfToken,
                "Accept": "application/json"
            },
            body: (method !== "GET" && method !== "HEAD")
                ? (data instanceof FormData ? data : JSON.stringify(data))
                : undefined
        };

        const response = await fetch(url, options);

        const token = response.headers.get("x-csrf-token");
        if (token) csrfToken = token;

        if (loader) loader.classList.add("hidden");

        if (!response.ok) {
            let message = `HTTP ${response.status}`;

            try {
                const errData = await response.json();
                message = errData?.error || errData?.message || message;
            } catch (_) {}

            throw new Error(message);
        }

        if (response.redirected) {
            return [];
        }
        return await response.json();

    } catch (err) {
        if (loader) loader.classList.add("hidden");
        throw err;
    }
}

function showError(error) {
    console.warn("API Fehler:", error);
    alert(error.message);
}
