<?php
$this->extends('Layout/layout', [
    'htmlTitle' => 'Passwort festlegen',
    'title' => 'Neues Passwort festlegen',
    'navId' => 'start',
]);
?>

<form method="post" id="passwordForm">
<?=$_csrfToken()->inputHidden()?>

<?php
$this->include('partials/alert', [
    'alertId' => 'AlertWiederholungFalsch',
    'type' => 'danger',
    'hide' => !$this->check($wiederholung_falsch),
    'text' => 'Die Wiederholung stimmt nicht mit dem Passwort überein.',
]);
?>

<p>Bitte wähle dein neues Passwort.</p>

<div class='form-group row '>
    <label for='input-password' class='col-sm-2 col-form-label'>Neues Passwort</label>
    <div class='col-sm-10'>
        <?=$password->input(id: 'input-password', type: 'password', placeholder: 'neues Passwort', required: true)?>
    </div>
</div>

<div class='form-group row '>
    <label for='input-password2' class='col-sm-2 col-form-label'>Passwort wiederholen</label>
    <div class='col-sm-10'>
        <?=$password2->input(id: 'input-password2', type: 'password', placeholder: 'Wiederholung', required: true)?>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-success">Speichern</button>
        <button type="reset" class="btn btn-default">Zurücksetzen</button>
    </div>
</div>

</form>

<script>
document.getElementById("passwordForm").addEventListener("submit", function (e) {
    let input1 = document.querySelector("[name='password']").value;
    let input2 = document.querySelector("[name='password2']").value;
    if (input1 !== input2) {
        document.getElementById("AlertWiederholungFalsch").classList.remove("hide");
        e.preventDefault();
    }
});
</script>
