<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>
<form method="post" action="?token=<?=$token?>" id="passwordForm">

<?php 
    Tpl::set('alert_id', 'AlertWiederholungFalsch');
    Tpl::set('alert_type', 'danger');
    Tpl::set('alert_hide', empty($wiederholung_falsch));
    Tpl::set('alert_text', 'Die Wiederholung stimmt nicht mit dem Passwort überein.');
    Tpl::render('Layout/alert');
 ?>            
            
<p>Bitte wähle dein neues Passwort.</p>

<div class='form-group row '>
    <label for='input-password' class='col-sm-2 col-form-label'>Neues Passwort</label>
    <div class='col-sm-10'>
        <input id='input-password' name='password' type='password' class='form-control' placeholder='neues Passwort' title='neues Passwort' required>
    </div>
</div>

<div class='form-group row '>
    <label for='input-password' class='col-sm-2 col-form-label'>Passwort wiederholen</label>
    <div class='col-sm-10'>
        <input id='input-password2' name='password2' type='password' class='form-control' placeholder='neues Passwort' title='neues Passwort'>
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
    let input1 = document.getElementById("input-password").value;
    let input2 = document.getElementById("input-password2").value;
    if (input1 !== input2) {
        document.getElementById("AlertWiederholungFalsch").classList.remove("hide");
        e.preventDefault();
    }
});
</script>
