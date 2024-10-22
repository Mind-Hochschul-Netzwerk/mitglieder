<?php

$this->extends('Layout/layout', [
    'htmlTitle' => 'Benutzerkonto aktivieren',
    'title' => 'Benutzerkonto aktivieren',
    'navId' => 'start',
]);

$alerts = [
    'usernameMissing' => 'Bitte wähle einen Benutzernamen.',
    'usernameInvalid' => 'Dein Benutzername enthält ungültige Zeichen.',
    'usernameUsed' => 'Dieser Benutzername wird bereits verwendet.',
    'emailUsed' => 'Die E-Mail-Adresse ' . $email . ' wird bereits von einem anderen Mitglied verwendet. Bitte wende dich an die Mitgliederbetreuung, um das Problem zu lösen.',
    'passwordMissing' => 'Bitte wähle ein Passwort.',
    'passwordMismatch' => 'Das Passwort und die Wiederholung stimmen nicht überein.',
];

foreach ($alerts as $name=>$text) {
    if (!empty($$name)) {
        $this->include('Layout/alert', [
            'alert_type' => 'danger',
            'alert_text' => $text,
        ]);
    }
}

?>

<p>Hallo <?=$data['mhn_vorname']?>,</p>
<p>wir freuen uns, dich als neues Mitglied im Mind-Hochschul-Netzwerk zu begrüßen! Bevor es losgehen kann, musst du deine Zugangsdaten festlegen und entscheiden, welche Daten du im Netzwerk freigeben möchtest.</p>

<form method="post">

<h3>Deine Zugangsdaten</h3>

<p>Mit deinem Benutzernamen (Anmeldenamen) und deinem Passwort meldest du dich in unserem Mitgliedernetzwerk an. Bitte beachte, dass du deinen Benutzernamen nachträglich nicht mehr ändern kannst.</p>

<div class='form-group row '>
    <label for='input-username' class='col-sm-2 col-form-label'>Benutzername</label>
    <div class='col-sm-10'>
        <input id='input-username' name='username' class='form-control'  title='Benutzername' value="<?=$username?>" required>
    </div>
</div>

<div class='form-group row '>
    <label for='input-password' class='col-sm-2 col-form-label'>Passwort</label>
    <div class='col-sm-10'>
        <input id='input-password' name='password' type='password' class='form-control' placeholder='neues Passwort' title='neues Passwort' required>
    </div>
</div>

<div class='form-group row '>
    <label for='input-password2' class='col-sm-2 col-form-label'>Passwort wiederholen</label>
    <div class='col-sm-10'>
        <input id='input-password2' name='password2' type='password' class='form-control' placeholder='neues Passwort' title='neues Passwort'>
    </div>
</div>

<h3>Deine Daten im Netzwerk</h3>

<p>
Unser Netzwerk lebt davon, dass die Mitglieder sich gegenseitig finden und
miteinander in Kontakt treten können. Lege fest, welche Daten du im Netzwerk
freigibst. Was du nicht freigibst, ist nur für bestimme Personen wie
Administrator:innen und die Mitgliederbetreuung sichtbar. Freigegebene Daten sind
nur für Mitglieder sichtbar. Außerdem können Mitglieder dich über freigegebene
Daten in der Mitgliedersuche finden.</p>

<p>
Deine angegeben Daten und die Freigabeeinstellungen kannst du jederzeit in deinem Mitgliedsprofil anpassen.
</p>

<div class="form-group row">
    <div class="col-sm-2">Meine Adresse (Hauptwohnsitz)</div>
    <div class="col-sm-10 ">
        <div><label><input type="radio" name="sichtbarkeit_adresse" value="1" required> für Mitglieder freigeben</label></div>
        <div><label><input type="radio" name="sichtbarkeit_adresse" value="2" required> eingeschränkt freigeben: nur PLZ, Ort und Land, aber nicht Straße/Hausnr. und Adresszusatz</label></div>
        <div><label><input type="radio" name="sichtbarkeit_adresse" value="0" required> nicht freigeben</label></div>
    </div>
</div>

<p>
Deine E-Mail-Adresse wird für die vereinsinterne Kommunikation benötigt, zum Beispiel für die Einladung zu Vorstandswahlen.

Außerdem haben wir einen monatlichen Newsletter und Diskussionsforen, deren Inhalte du an deine E-Mail-Adresse abonnieren kannst.

Über unsere Webseite können sich alle Mitglieder gegenseitig
Direktnachrichten schicken, die per E-Mail zugestellt werden. Die E-Mail-Adressen werden dabei
nicht preisgegeben.

Darüber hinaus kannst du deine E-Mail-Adresse freigeben, sodass Mitglieder dich auch direkt per E-Mail anschreiben können.
</p>

<div class="form-group row">
    <div class="col-sm-2">E-Mail-Adresse</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="sichtbarkeit_email" value="1" required> für Mitglieder freigeben</label>
        <label><input type="radio" name="sichtbarkeit_email" value="0" required> nicht freigeben</label>
    </div>
</div>

<p>Entscheide für deine weiteren Daten, ob andere Mitglieder sie sehen dürfen.</p>

<div class="form-group row">
    <div class="col-sm-2">Telefonnummer</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="sichtbarkeit_telefon" value="1" required> für Mitglieder freigeben</label>
        <label><input type="radio" name="sichtbarkeit_telefon" value="0" required> nicht freigeben</label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-2">Geburtsdatum / Alter</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="sichtbarkeit_geburtstag" value="1" required> für Mitglieder freigeben</label>
        <label><input type="radio" name="sichtbarkeit_geburtstag" value="0" required> nicht freigeben</label>
    </div>
</div>

<?php if ($data['mhn_mensa_nr']): ?>
<div class="form-group row">
    <div class="col-sm-2">Mensa-Nummer</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="sichtbarkeit_mensa_nr" value="1" required> für Mitglieder freigeben</label>
        <label><input type="radio" name="sichtbarkeit_mensa_nr" value="0" required> nicht freigeben</label>
    </div>
</div>
<?php endif; ?>

<div class="form-group row">
    <div class="col-sm-2">Ausbildung und Studium</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="sichtbarkeit_studium" value="1" required> für Mitglieder freigeben</label>
        <label><input type="radio" name="sichtbarkeit_studium" value="0" required> nicht freigeben</label>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-2">Beruf und Tätigkeit</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="sichtbarkeit_beruf" value="1" required> für Mitglieder freigeben</label>
        <label><input type="radio" name="sichtbarkeit_beruf" value="0" required> nicht freigeben</label>
    </div>
</div>

<p>Bei den folgenden Daten kannst du nicht auswählen, ob sie freigegeben werden sollen. Wenn du nicht möchtest,
dass andere Mitglieder sie sehen können, kannst du sie aus deinem Profil löschen.</p>

<?php if ($data['mhn_titel']): ?>
<div class="form-group row">
    <div class="col-sm-2">akademischer Titel</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="uebernahme_titel" value="1" required> in mein Profil übernehmen</label>
        <label><input type="radio" name="uebernahme_titel" value="0" required> Angaben löschen</label>
    </div>
</div>
<?php endif; ?>

<?php if ($data['mhn_homepage']): ?>
<div class="form-group row">
    <div class="col-sm-2">Homepage</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="uebernahme_homepage" value="1" required> in mein Profil übernehmen</label>
        <label><input type="radio" name="uebernahme_homepage" value="0" required> Angaben löschen</label>
    </div>
</div>
<?php endif; ?>

<?php if ($data['mhn_zws_strasse'] || $data['mhn_zws_zusatz'] || $data['mhn_zws_plz'] || $data['mhn_zws_ort'] || $data['mhn_zws_land']): ?>
<div class="form-group row">
    <div class="col-sm-2">Zweitwohnsitz</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="uebernahme_zweitwohnsitz" value="1" required> in mein Profil übernehmen</label>
        <label><input type="radio" name="uebernahme_zweitwohnsitz" value="1" required> nur PLZ, Ort, Land übernehmen</label>
        <label><input type="radio" name="uebernahme_zweitwohnsitz" value="0" required> Angaben löschen</label>
    </div>
</div>
<?php endif; ?>

<div class="form-group row">
    <div class="col-sm-2">Sprachen, Hobbies, Interessen, Ehrenamt</div>
    <div class="col-sm-10 ">
        <label><input type="radio" name="uebernahme_interessen" value="1" required> in mein Profil übernehmen</label>
        <label><input type="radio" name="uebernahme_interessen" value="0" required> Angaben löschen</label>
    </div>
</div>

<p>Außerdem sind sichtbar für alle Mitglieder: Dein Name, deine MHN-Mitgliedsnummer, dein Aufnahmedatum (heute), deine Angaben, worüber du Auskunft erteilst und bei welchen Aufgaben du helfen könntest.</p>

<p>Nach du deinen Zugang angelegt hast, kannst du deinem Profil auch ein Foto von dir hinzufügen.</p>

<div class="form-group row">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-success">MHN-Benutzerzugang anlegen</button>
    </div>
</div>

</form>