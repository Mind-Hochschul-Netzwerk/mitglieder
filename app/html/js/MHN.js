$(function() {
    $('[data-tooltip="sichtbarkeit"] .toggle-group .toggle-on').prop("title", "sichtbar für andere Mitglieder");
    $('[data-tooltip="sichtbarkeit"] .toggle-group .toggle-off').prop("title", "nicht sichtbar");
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
function callApi(method = "GET", url = "", data = {}, loader = undefined) {
    if (loader) {
        loader.classList.remove("hidden");
    }
    let options = {
        method: method,
        headers: {
            "X-CSRF-Token": csrfToken
        },
        body: (method !== "GET" && method !== "HEAD") ? (
            (data instanceof FormData ? data : JSON.stringify(data))
        ) : undefined
    };
    return fetch(url, options).then(response => {
        if (loader) {
            loader.classList.add("hidden");
        }

        const token = response.headers.get("x-csrf-token");
        if (token) {
            csrfToken = token;
        }
        if (!response.ok) {
            throw new Error(JSON.stringify(response));
        }
        return response.json();
    });
}
