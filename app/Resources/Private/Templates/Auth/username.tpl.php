<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>
<form method="post">

<?php 
    if (!empty($username_existiert)) {
        Tpl::set('alert_type', 'danger');
        Tpl::set('alert_text', 'Der Benutzername ist schon vergeben. Bitte wähle einen anderen.');
        Tpl::render('Layout/alert');
    }

    if (!empty($username_zeichen)) {
        Tpl::set('alert_type', 'danger');
        Tpl::set('alert_text', 'Der Benutzername enthält ungültige Zeichen.');
        Tpl::render('Layout/alert');
    }
 ?>            
            
<p>Wähle bitte deinen Benutzernamen. Er kann später nicht mehr geändet werden.</p>

<div class='form-group row '>
    <label for='input-username' class='col-sm-2 col-form-label'>Benutzername</label>
    <div class='col-sm-10'>
        <input id='input-username' name='username' value='<?=$username?>' class='form-control' placeholder='Benutzername' title='Benutzername'>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-success">Speichern</button>
        <button type="reset" class="btn btn-default">Zurücksetzen</button>
    </div>
</div>

</form>

