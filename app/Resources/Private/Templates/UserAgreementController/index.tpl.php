<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'Einwilligungen',
    'navId' => 'suche',
    'title' => 'Einwilligungen',
]);
?>

<style>
.agreement {
    border: solid 1px #ccc;
    border-radius: 5px;
    padding: 5px;
    background-color: #f8f8f8;
    overflow-y: auto;
    height: 150px;
    resize: vertical;
    margin-bottom: 10px;
}
.agreement_info {
    color: #999;
}
dialog {
    border: solid 1px #666;
    border-radius: 5px;
    z-index: 2;
}
dialog::backdrop {
    backdrop-filter: blur(2px);
}
</style>

<div id="Kenntnisnahme">
    <h3>Kenntnisnahme der Datenverarbeitung</h3>
    <div class="agreement" resizable></div>
    <div class="timestamp">Du hast am <span></span> erklärt, diesen Text zur Kenntnis genommen zu haben.</div>
</div>

<div id="Einwilligung">
    <h3>Einwilligung zur Datenverarbeitung</h3>
    <div class="agreement" resizable></div>
    <div class="timestamp">Du hast am <span></span> eingewilligt.</div>
</div>

<h3>Verpflichtung zum Datenschutz</h3>

<form id="datenschutzverpflichtung">
    <input type="hidden" name="name" value="Datenschutzverpflichtung">
    <input type="hidden" name="id" value="">

    <div class="agreement" resizable></div>

    <div class="loader hidden"></div>

    <div id="datenschutzverpflichtung_timestamp" class="hidden">
        Du hast am <span></span> eingewilligt.
        <div id="datenschutzverpflichtung_update" class="hidden">
            Es liegt ein neuer Text vom <span></span> vor. Bitte aktualisiere deine Einwilligung. Andernfalls können dir Berechtigungen entzogen werden, für die Zustimmung zum aktualisierten Text nötig ist.
            <button class='btn btn-primary' type="button">Anzeigen</button>
        </div>
        <button class='btn btn-danger' type="submit" name="action" value="revoke">Einwilligung widerrufen</button>
    </div>
    <div id="datenschutzverpflichtung_accept" class="hidden">
        Du hast noch nicht eingewilligt. Du kannst deine Einwilligung jederzeit widerrufen.
        <button class='btn btn-primary' type="submit" name="action" value="accept">Ja, ich stimme zu.</button>
    </div>
</form>

<dialog id="datenschutzverpflichtung_dialog"><form>
    <input type="hidden" name="name" value="Datenschutzverpflichtung">
    <input type="hidden" name="id" value="">
    <div class="agreement" resizable></div>
    <button class='btn btn-primary' type="submit" name="action" value="accept">Ja, ich stimme zu.</button>
    <button class='btn btn-default' type="button">Erstmal nicht</button>
</form></dialog>

<script>

const divKenntnisnahme = document.getElementById("Kenntnisnahme");
const divEinwilligung = document.getElementById("Einwilligung");

const datenschutzverpflichtung_form = document.getElementById('datenschutzverpflichtung');
const datenschutzverpflichtung_text = datenschutzverpflichtung_form.querySelector('.agreement');
const datenschutzverpflichtung_timestamp = document.getElementById('datenschutzverpflichtung_timestamp');
const datenschutzverpflichtung_accept = document.getElementById('datenschutzverpflichtung_accept');
const datenschutzverpflichtung_update = document.getElementById('datenschutzverpflichtung_update');
const datenschutzverpflichtung_dialog = document.getElementById('datenschutzverpflichtung_dialog');
const loader = document.querySelector(".loader");

datenschutzverpflichtung_update.querySelector("button").addEventListener("click", event => {
    datenschutzverpflichtung_dialog.showModal();
})

datenschutzverpflichtung_dialog.querySelector("button[type=button]").addEventListener("click", event => {
    datenschutzverpflichtung_dialog.close();
})

function submit(event) {
    event.preventDefault();
    action = event.submitter.value;
    if (action === "revoke") {
        if (!confirm("Wenn du deine Zustimmung widerrufst, können dir Berechtigungen entzogen werden, für die die Einwilligung nötig ist. Möchtest du die Zustimmung widerrufen?")) {
            return;
        }
    }

    callApi("POST", `/user/<?=$user->get('username')?>/agreements/${event.target.elements.id.value}`, {
        action: action
    }, loader).then(data => handleResponse(data))
    .catch((error) => {
        alert("Beim Speichern ist ein Fehler aufgetreten.");
    });
}

datenschutzverpflichtung_form.addEventListener("submit", (e) => submit(e));

datenschutzverpflichtung_dialog.querySelector("form").addEventListener("submit", (e) => {
    datenschutzverpflichtung_dialog.close();
    submit(e);
});

function showAgreement(element, agreement) {
    const id = element.querySelector("input[name=id]");
    if (id) {
        id.value = agreement.id;
    }
    element.querySelector(".agreement").innerHTML = marked.parse(agreement.text);
    element.querySelector(".agreement").appendChild(Object.assign(
        document.createElement("p"), {
            classList: ["agreement_info"],
            textContent: "Version " + agreement.version + " vom " + formatTime(agreement.textTimestamp, true, false)
        })
    );
}

function handleResponse(data) {
    datenschutzverpflichtung_timestamp.classList.add("hidden");
    datenschutzverpflichtung_accept.classList.add("hidden");
    datenschutzverpflichtung_update.classList.add("hidden");

    if (data.state.Datenschutzverpflichtung && data.state.Datenschutzverpflichtung.action === "accept") {
        const agreement = data.state.Datenschutzverpflichtung;
        showAgreement(datenschutzverpflichtung_form, agreement);

        datenschutzverpflichtung_timestamp.classList.remove("hidden");
        datenschutzverpflichtung_timestamp.querySelector("span").innerHTML = formatTime(agreement.timestamp, true, false);

        if (data.latest.Datenschutzverpflichtung.version > data.state.Datenschutzverpflichtung.version) {
            datenschutzverpflichtung_update.classList.remove("hidden");
            datenschutzverpflichtung_update.querySelector("span").innerHTML = formatTime(data.latest.Datenschutzverpflichtung.textTimestamp, true, false);
            showAgreement(datenschutzverpflichtung_dialog.querySelector("form"), data.latest.Datenschutzverpflichtung);
        }
    } else {
        showAgreement(datenschutzverpflichtung_form, data.latest.Datenschutzverpflichtung);
        datenschutzverpflichtung_accept.classList.remove("hidden");
    }

    if (data.state.Kenntnisnahme) {
        showAgreement(divKenntnisnahme, data.state.Kenntnisnahme);
        if (Date.parse(data.state.Kenntnisnahme.timestamp)) {
            divKenntnisnahme.querySelector(".timestamp span").innerText = formatTime(data.state.Kenntnisnahme.timestamp, true, false);
        } else {
            divKenntnisnahme.querySelector(".timestamp").classList.add("hidden");
        }
    }

    if (data.state.Einwilligung) {
        showAgreement(divEinwilligung, data.state.Einwilligung);
        if (Date.parse(data.state.Einwilligung.timestamp)) {
            divEinwilligung.querySelector(".timestamp span").innerText = formatTime(data.state.Einwilligung.timestamp, true, false);
        } else {
            divEinwilligung.querySelector(".timestamp").classList.add("hidden");
        }
    }
}

callApi("GET", "/user/<?=$user->get('username')?>/agreements/index", {}, loader).then((data) => handleResponse(data));

</script>
