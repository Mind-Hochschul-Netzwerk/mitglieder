<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'Einwilligungstexte',
    'navId' => 'suche',
    'title' => 'Einwilligungstexte',
]);
?>

<style>
#agreements td {
    cursor: pointer;
}
#agreements tr:has(td:hover) {
    background: #eee;
    text-decoration: underline;
}
#agreements tr.active {
    font-weight: bold;
    color: purple;
}
</style>

<form id="agreement">
    <input class="form__name" type="hidden">
    <h3 id="name"></h3>
    <div class="form-group row"><div class="col-sm-12">
        <textarea class='form__text form-control' rows="10"></textarea>
    </div></div>
    <div class="form-group row"><div class="col-sm-12">
        <button class='btn btn-primary'>Als neuste Version speichern</button>
    </div></div>
</form>

<h3>Texte</h3>

<table class="table" id="agreements">
    <thead><tr><th>Bezeichnung</th><th>Version</th><th>Datum</th><th>Zustimmungen<sup>1</sup></th></tr></thead>
    <tbody></tbody>
</table>


<div class="loader hidden"></div>

<p><sup>1</sup> Jedes Mitglied wird pro Einwilligungstext nur bei der neusten zugestimmten Version gezählt.</p>

<template id="rowTemplate">
    <tr><td class="name"></td><td class="version"></td><td class="timestamp"></td><td class="count"></td></tr>
</template>

<script>
const table = document.querySelector('#agreements tbody');
const form = document.getElementById('agreement');
const name = form.querySelector(".form__name");
const nameDisplay = document.getElementById("name");
const text = form.querySelector(".form__text");
const button = form.querySelector("button");
const template = document.getElementById('rowTemplate');
const loader = document.querySelector(".loader");

form.addEventListener("submit", (e) => {
    e.preventDefault();
    table.innerHTML = "";
    callApi("POST", "/agreements/api", {
        name: name.value,
        text: text.value
    }, loader)
    .then((data) => handleResponse(data))
    .catch((error) => {
        alert("Beim Speichern der Daten ist ein Fehler aufgetreten.");
    });
});

text.addEventListener("input", (e) => {
    button.disabled = text.dataset.value === text.value;
});

function loadAgreement(agreement) {
    name.value = agreement.name;
    text.value = agreement.text;
    text.dataset.value = agreement.text;
    nameDisplay.innerText = `${agreement.name} (Version ${agreement.version} vom ${formatTime(agreement.timestamp)})`;
    button.disabled = true;
}

function handleResponse(data) {
    if (data.length > 0) {
        loadAgreement(data[0]);
    }
    data.forEach(agreement => {
        const clone = template.content.cloneNode(true);
        clone.querySelector('.name').textContent = agreement.name;
        clone.querySelector('.version').textContent = agreement.version;
        clone.querySelector('.timestamp').textContent = formatTime(agreement.timestamp);
        clone.querySelector('.count').textContent = agreement.count;
        clone.querySelector('tr').addEventListener("click", e => {
            loadAgreement(agreement);
            table.querySelector('tr.active').classList.remove('active');
            e.currentTarget.classList.add('active');
        });

        table.appendChild(clone);
    });
    table.querySelector("tr").classList.add("active");
}

callApi("GET", "/agreements/api", {}, loader).then((data) => handleResponse(data));
</script>
