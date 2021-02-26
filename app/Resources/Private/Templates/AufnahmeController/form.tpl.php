<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

$alerts = [
    'usernameMissing' => 'Bitte wähle einen Benutzernamen.',
    'usernameInvalid' => 'Dein Benutzername enthält ungültige Zeichen.',
    'usernameUsed' => 'Dieser Benutzername wird bereits verwendet.',
    'passwordMissing' => 'Bitte wähle ein Passwort.',
    'passwordMismatch' => 'Das Passwort und die Wiederholung stimmen nicht überein.',
];

foreach ($alerts as $name=>$text) {
    if (!empty($$name)) {
        \MHN\Mitglieder\Tpl::set('alert_type', 'danger');
        \MHN\Mitglieder\Tpl::set('alert_text', $text);
        \MHN\Mitglieder\Tpl::render('Layout/alert');
    }
}

?>
<p>Hallo <?=$vorname?>,</p>
<p>wir freuen uns, dich als neues Mitglied im Mind-Hochschul-Netzwerk zu begrüßen!</p>
<p>Bevor es losgehen kann, musst du deine Zugangsdaten festlegen.</p>

<form action="?token=<?=$token?>" method="post">

<div class='form-group row '>
    <label for='input-password' class='col-sm-2 col-form-label'>Benutzername</label>
    <div class='col-sm-10'>
        <input id='input-password' name='username' class='form-control'  title='Benutzername' value="<?=$username?>" required>
    </div>
</div>

<p>Bitte beachte, dass du deinen Benutzernamen nachträglich nicht mehr ändern kannst.</p>

<div class='form-group row '>
    <label for='input-password' class='col-sm-2 col-form-label'>Passwort</label>
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
        <button type="submit" class="btn btn-success">Zugangsdaten speichern</button>
        <button type="reset" class="btn btn-default">Zurücksetzen</button>
    </div>
</div>

</form>