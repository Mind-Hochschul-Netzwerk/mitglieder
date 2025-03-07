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
</style>

<?php if ($user->get('kenntnisnahme_datenverarbeitung_aufnahme')): ?>
    <h4>Kenntnisnahme zur Datenverarbeitung (Aufnahmetool)</h4>
    <div class="row">
        <div class="col-sm-12"><?=$user->get('kenntnisnahme_datenverarbeitung_aufnahme_text')->textarea(disabled: true)?></textarea></div>
    </div>
    <div class="row">
        <div class="col-sm-10">zur Kenntnis genommen am <?=$user->get('kenntnisnahme_datenverarbeitung_aufnahme')->format('d.m.Y, H:i:s')?> Uhr</div>
    </div>
<?php endif; ?>

<?php if ($user->get('einwilligung_datenverarbeitung_aufnahme')): ?>
    <h4>Einwilligung zur Datenverarbeitung (Aufnahmetool)</h4>
    <div class="row">
        <div class="col-sm-12"><?=$user->get('einwilligung_datenverarbeitung_aufnahme_text')->textarea(disabled: true)?></textarea></div>
    </div>
    <div class="row">
        <div class="col-sm-10">eingewilligt am <?=$user->get('einwilligung_datenverarbeitung_aufnahme')->format('d.m.Y, H:i:s')?> Uhr</div>
    </div>
<?php endif; ?>

<h4>Verpflichtung zum Datenschutz</h4>

<form id="datenschutzverpflichtung">
    <input type="hidden" name="name" value="Datenschutzverpflichtung">
    <input type="hidden" name="id" value="">

    <div class="agreement" resizable></div>

    <div class="loader hidden"></div>

    <div id="datenschutzverpflichtung_timestamp">
        Du hast am <span></span> zugestimmt.
        <div id="datenschutzverpflichtung_update">
            Es liegt ein neuer Text vom <span></span> vor. Bitte aktualisiere deine Einwilligung. Andernfalls können dir Berechtigungen entzogen werden, für die Zustimmung zum aktualisierten Text nötig ist.
            <button class='btn btn-primary' type="button">Anzeigen</button>
        </div>
        <button class='btn btn-danger' type="submit" name="action" value="revoke">Einwilligung widerrufen</button>
    </div>
    <div id="datenschutzverpflichtung_accept">
        Du hast noch nicht zugestimmt. Du kannst deine Einwilligung jederzeit widerrufen.
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

function showAgreement(formElement, agreement) {
    formElement.elements.id.value = agreement.id;
    formElement.querySelector(".agreement").innerHTML = marked.parse(agreement.text);
    formElement.querySelector(".agreement").appendChild(Object.assign(
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
}

callApi("GET", "/user/<?=$user->get('username')?>/agreements/index", {}, loader).then((data) => handleResponse(data));

</script>
