<?php

use App\Service\TemplateVariable;

$this->extends('Layout/layout', [
    'htmlTitle' => 'Benutzerkonto aktivieren',
    'title' => 'Benutzerkonto aktivieren',
    'navId' => 'start',
]);

$alerts = [
    'usernameMissing' => 'Bitte wähle einen Benutzernamen.',
    'usernameInvalid' => 'Dein Benutzername enthält ungültige Zeichen.',
    'usernameUsed' => 'Dieser Benutzername wird bereits verwendet.',
    'emailUsed' => "Die E-Mail-Adresse $email wird bereits von einem anderen Mitglied verwendet. Bitte wende dich an die Mitgliederbetreuung, um das Problem zu lösen.",
    'passwordMissing' => 'Bitte wähle ein Passwort.',
    'passwordMismatch' => 'Das Passwort und die Wiederholung stimmen nicht überein.',
];

foreach ($alerts as $name=>$text) {
    if ($this->check($$name)) {
        $this->include('partials/alert', [
            'type' => 'danger',
            'text' => $text,
        ]);
    }
}

// extract
foreach ($data as $k=>$v) {
    $$k = $v;
}

?>

<p>Hallo <?=$mhn_vorname?>,</p>
<p>wir freuen uns, dich als neues Mitglied im Mind-Hochschul-Netzwerk zu begrüßen! Bevor es losgehen kann, musst du deine Zugangsdaten festlegen und entscheiden, welche Daten du im Netzwerk freigeben möchtest.</p>

<form method="post">
<?=$_csrfToken()->inputHidden()?>

<h3>Deine Zugangsdaten</h3>

<p>Mit deinem Benutzernamen (Anmeldenamen) und deinem Passwort meldest du dich in unserem Mitgliedernetzwerk an. Bitte beachte, dass du deinen Benutzernamen nachträglich nicht mehr ändern kannst.</p>

<div class='form-group row '>
    <label for='input-username' class='col-sm-2 col-form-label'>Benutzername</label>
    <div class='col-sm-10'><?=$username->input()?></div>
</div>

<div class='form-group row '>
    <label for='input-password' class='col-sm-2 col-form-label'>Passwort</label>
    <div class='col-sm-10'>
        <?=$password->input(id: 'input-password', type: 'password', required: true, placeholder: 'neues Passwort', autoComplete: 'new-password')?>
    </div>
</div>

<div class='form-group row '>
    <label for='input-password2' class='col-sm-2 col-form-label'>Passwort wiederholen</label>
    <div class='col-sm-10'>
        <?=$password2->input(id: 'input-password2', type: 'password', required: true, placeholder: 'neues Passwort')?>
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
        <div><?=$sichtbarkeit_adresse->box(type: 'radio', value: 1, required: true, label: "für Mitglieder freigeben")?></div>
        <div><?=$sichtbarkeit_adresse->box(type: 'radio', value: 2, required: true, label: "eingeschränkt freigeben: nur PLZ, Ort und Land, aber nicht Straße/Hausnr. und Adresszusatz")?></div>
        <div><?=$sichtbarkeit_adresse->box(type: 'radio', value: 0, required: true, label: "nicht freigeben")?></div>
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
        <?=$sichtbarkeit_email->box(type: 'radio', value: 1, required: true, label: "für Mitglieder freigeben")?>
        <?=$sichtbarkeit_email->box(type: 'radio', value: 0, required: true, label: "nicht freigeben")?>
    </div>
</div>

<p>Entscheide für deine weiteren Daten, ob andere Mitglieder sie sehen dürfen.</p>

<div class="form-group row">
    <div class="col-sm-2">Telefonnummer</div>
    <div class="col-sm-10 ">
        <?=$sichtbarkeit_telefon->box(type: 'radio', value: 1, required: true, label: "für Mitglieder freigeben")?>
        <?=$sichtbarkeit_telefon->box(type: 'radio', value: 0, required: true, label: "nicht freigeben")?>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-2">Geburtsdatum / Alter</div>
    <div class="col-sm-10 ">
        <?=$sichtbarkeit_geburtstag->box(type: 'radio', value: 1, required: true, label: "für Mitglieder freigeben")?>
        <?=$sichtbarkeit_geburtstag->box(type: 'radio', value: 0, required: true, label: "nicht freigeben")?>
    </div>
</div>

<?php if ("$mhn_mensa_nr"): ?>
<div class="form-group row">
    <div class="col-sm-2">Mensa-Nummer</div>
    <div class="col-sm-10 ">
        <?=$sichtbarkeit_mensa_nr->box(type: 'radio', value: 1, required: true, label: "für Mitglieder freigeben")?>
        <?=$sichtbarkeit_mensa_nr->box(type: 'radio', value: 0, required: true, label: "nicht freigeben")?>
    </div>
</div>
<?php endif; ?>

<div class="form-group row">
    <div class="col-sm-2">Ausbildung und Studium</div>
    <div class="col-sm-10 ">
        <?=$sichtbarkeit_studium->box(type: 'radio', value: 1, required: true, label: "für Mitglieder freigeben")?>
        <?=$sichtbarkeit_studium->box(type: 'radio', value: 0, required: true, label: "nicht freigeben")?>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-2">Beruf und Tätigkeit</div>
    <div class="col-sm-10 ">
        <?=$sichtbarkeit_beruf->box(type: 'radio', value: 1, required: true, label: "für Mitglieder freigeben")?>
        <?=$sichtbarkeit_beruf->box(type: 'radio', value: 0, required: true, label: "nicht freigeben")?>
    </div>
</div>

<p>Bei den folgenden Daten kannst du nicht auswählen, ob sie freigegeben werden sollen. Wenn du nicht möchtest,
dass andere Mitglieder sie sehen können, kannst du sie aus deinem Profil löschen.</p>

<?php if ("$mhn_titel"): ?>
<div class="form-group row">
    <div class="col-sm-2">akademischer Titel</div>
    <div class="col-sm-10 ">
        <?=$uebernahme_titel->box(type: 'radio', value: 1, required: true, label: "in mein Profil übernehmen")?>
        <?=$uebernahme_titel->box(type: 'radio', value: 0, required: true, label: "Angaben löschen")?>
    </div>
</div>
<?php endif; ?>

<?php if ("$mhn_homepage"): ?>
<div class="form-group row">
    <div class="col-sm-2">Homepage</div>
    <div class="col-sm-10 ">
        <?=$uebernahme_homepage->box(type: 'radio', value: 1, required: true, label: "in mein Profil übernehmen")?>
        <?=$uebernahme_homepage->box(type: 'radio', value: 0, required: true, label: "Angaben löschen")?>
    </div>
</div>
<?php endif; ?>

<?php if ("$mhn_zws_strasse$mhn_zws_zusatz$mhn_zws_plz$mhn_zws_ort$mhn_zws_land"): ?>
<div class="form-group row">
    <div class="col-sm-2">Zweitwohnsitz</div>
    <div class="col-sm-10 ">
        <?=$uebernahme_zweitwohnsitz->box(type: 'radio', value: 1, required: true, label: "in mein Profil übernehmen")?>
        <?=$uebernahme_zweitwohnsitz->box(type: 'radio', value: 2, required: true, label: "nur PLZ, Ort, Land übernehmen")?>
        <?=$uebernahme_zweitwohnsitz->box(type: 'radio', value: 0, required: true, label: "Angaben löschen")?>
    </div>
</div>
<?php endif; ?>

<div class="form-group row">
    <div class="col-sm-2">Sprachen, Hobbies, Interessen, Ehrenamt</div>
    <div class="col-sm-10 ">
        <?=$uebernahme_interessen->box(type: 'radio', value: 1, required: true, label: "in mein Profil übernehmen")?>
        <?=$uebernahme_interessen->box(type: 'radio', value: 0, required: true, label: "Angaben löschen")?>
    </div>
</div>

<p>Außerdem sind sichtbar für alle Mitglieder: Dein Name, deine MHN-Mitgliedsnummer, dein Aufnahmedatum (heute), deine Angaben, worüber du Auskunft erteilst und bei welchen Aufgaben du helfen könntest.</p>

<p>Nachdem du deinen Zugang angelegt hast, kannst du deinem Profil auch ein Foto von dir hinzufügen.</p>

<div class="form-group row">
    <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-success">MHN-Benutzerzugang anlegen</button>
    </div>
</div>

</form>
