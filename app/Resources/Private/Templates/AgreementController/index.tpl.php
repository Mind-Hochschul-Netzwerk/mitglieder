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
    <div class="form-group row">
        <div class="col-sm-2"><label>Bezeichnung:</label></div>
        <div class="col-sm-10"><!--<input class='form__name form-control'>-->
        <select class='form__name form-control'></select>
    </div>
    </div>
    <div class="form-group row"><div class="col-sm-12">
        <textarea class='form__text form-control' rows="10"></textarea>
    </div></div>
    <div class="form-group row"><div class="col-sm-12">
        <button class='btn btn-primary'>Als neuste Version speichern</button>
    </div></div>
</form>

<h3>Versionen</h3>

<table class="table" id="agreements">
    <thead><tr><th>Bezeichnung</th><th>Version</th><th>Datum</th></tr></thead>
    <tbody></tbody>
</table>

<div class="loader hidden"></div>

<template id="rowTemplate">
    <tr><td class="name"></td><td class="version"></td><td class="timestamp"></td></tr>
</template>

<script>

const table = document.querySelector('#agreements tbody');
const form = document.getElementById('agreement');
const name = form.querySelector(".form__name");
const text = form.querySelector(".form__text");
const button = form.querySelector("button");
const template = document.getElementById('rowTemplate');
const loader = document.querySelector(".loader");
let csrfToken = "";

form.addEventListener("submit", (e) => {
    e.preventDefault();
    fetch("/agreements", {
        method: "POST",
        headers: {
            "X-CSRF-Token": csrfToken
        },
        body: JSON.stringify({
            name: name.value,
            text: text.value
        })
    })
    .then((response) => {
        if (!response.ok) {
            throw new Error("");
        }
        csrfToken = response.headers.get("x-csrf-token");
        return response.json();
    })
    .then((data) => handleResponse(data))
    .catch((error) => {
        alert("Beim Speichern der Daten ist ein Fehler aufgetreten.");
    });
});

text.addEventListener("input", (e) => {
    button.disabled = text.dataset.value === text.value;
});

function loadAgreement(name, value) {
    name.value = name;
    text.value = value;
    text.dataset.value = value;
    button.disabled = true;
}

function handleResponse(data) {
    loader.classList.add("hidden");
    table.innerHTML = "";

    const names = [...new Set(data.map(item => item.name))];
    names.forEach(item => name.appendChild(Object.assign(
        document.createElement("option"), {textContent: item})
    ));


    if (data.length > 0) {
        loadAgreement(data[0].name, data[0].text);

    }
    data.forEach(agreement => {
        const clone = template.content.cloneNode(true);
        clone.querySelector('.name').textContent = agreement.name;
        clone.querySelector('.version').textContent = agreement.version;
        clone.querySelector('.timestamp').textContent = formatTime(new Date(agreement.timestamp));
        clone.querySelector('tr').addEventListener("click", e => {
            loadAgreement(agreement.name, agreement.text);
            table.querySelector('tr.active').classList.remove('active');
            e.currentTarget.classList.add('active');
        });

        table.appendChild(clone);
    });
    table.querySelector("tr").classList.add("active");
}

loader.classList.remove("hidden");
fetch('/agreements/index')
.then((response) => {
    csrfToken = response.headers.get("x-csrf-token");
    return response.json();
})
.then((data) => handleResponse(data));

</script>
