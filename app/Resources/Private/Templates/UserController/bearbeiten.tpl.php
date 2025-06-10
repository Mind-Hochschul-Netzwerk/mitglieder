<?php

use App\Service\TemplateVariable;

$this->extends('Layout/layout', [
    'title' => "Meine Mitgliedsdaten im MHN <small><span class='glyphicon glyphicon-user'></span> <a href='/user/$username'>Profil anzeigen</a></small>",
    'htmlTitle' => 'Mein Profil',
    'navId' => 'bearbeiten',
]);

function form_row($label, $inputs) {
    $html = '';

    foreach ($inputs as $inputAndCols) {
        [$input, $cols] = is_array($inputAndCols) ? $inputAndCols : [$inputAndCols, 10];
        $html .= "<div class='col-sm-$cols'>$input</div>";
    }

    return "<div class='form-group row'><label class='col-sm-2 col-form-label'>$label</label>$html</div>\n";
}

if (!$this->check($active_pane)) {
    $active_pane = 'basisdaten';
}

$changes = $error = $password_error = false;

// Alerts generieren

if ($this->check($profilbild_format_unbekannt)) {
    $this->include('partials/alert', [
        'type' => 'danger',
        'text' => 'Das Dateiformat des Profilbilds wurde nicht erkannt oder wird nicht unterstützt. Unterstützte Formate: JPEG, PNG.',
    ]);
    $active_pane = 'profilbild';
    $changes = $error = true;
}

if ($this->check($profilbild_uploadfehler)) {
    $this->include('partials/alert', [
        'type' => 'danger',
        'text' => 'Beim Upload des Profilbilds ist es leider zu einem unbekannten Fehler gekommen. Bitte wende dich an die Mitgliederbetreuung.',
    ]);
    $active_pane = 'profilbild';
    $changes = $error = true;
}

if ($this->check($email_error)) {
    $this->include('partials/alert', [
        'type' => 'danger',
        'text' => 'Die eigegebene E-Mail-Adresse ist ungültig. Die E-Mail-Adresse und wurde nicht gespeichert.',
    ]);
    $changes = $error = true;
} else {
    $email_error = false;
}

if ($this->check($email_changed)) {
    $this->include('partials/alert', [
        'type' => 'success',
        'text' => 'Deine E-Mail-Adresse wurde erfolgreich geändert.',
    ]);
}

$this->include('partials/alert', [
    'alertId' => 'AlertWiederholungFalsch',
    'type' => 'danger',
    'hide' => !$this->check($new_password2_error),
    'text' => 'Die Wiederholung stimmt nicht mit dem neuen Passwort überein.',
]);

if ($this->check($new_password2_error)) {
    $active_pane = 'basisdaten';
    $password_error = $changes = $error = true;
}

if ($this->check($old_password_error)) {
    $this->include('partials/alert', [
        'type' => 'danger',
        'text' => 'Das alte Passwort ist falsch. Das Passwort wurde nicht geändert.',
    ]);
    $active_pane = 'basisdaten';
    $password_error = $changes = $error = true;
}

if ($this->check($data_saved_info)) {
    $this->include('partials/alert', [
        'type' => 'success',
        'text' => (!$error ? 'Deine Daten wurden geändert.' : 'Die anderen Änderungen wurden gespeichert.') . (!empty($email_auth_info) ? ' Bitte schau in dein E-Mail-Postfach, um die Änderung deiner E-Mail-Adresse abzuschließen.' : ''),
    ]);
}

if ($this->check($errorMessage)) {
    $this->include('partials/alert', [
        'type' => 'warning',
        'text' => $errorMessage,
    ]);
}
?>

<ul class="nav nav-tabs">
    <li <?=$active_pane == 'basisdaten' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#basisdaten">Basisdaten</a></li>
    <li <?=$active_pane == 'uebermich' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#uebermich">Über mich</a></li>
    <li <?=$active_pane == 'ausbildungberuf' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#ausbildungberuf">Ausbildung und Beruf</a></li>
    <li <?=$active_pane == 'profilbild' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#profilbild">Profilbild</a></li>
    <li <?=$active_pane == 'settings' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#settings">Netzwerk</a></li>
    <li <?=$active_pane == 'account' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#account">MHN-Mitgliedskonto</a></li>
</ul>

<form enctype="multipart/form-data" method="post" id="profile-form">
<?=$_csrfToken()->inputHidden()?>

<div class="tab-content">
    <div class="tab-pane <?=$active_pane == 'basisdaten' ? 'active' : ''?>" id="basisdaten">
        <h3>Basisdaten</h3>
        <p>Diese Basisdaten werden von allen Mitgliedern erhoben. Mit der Sichtbarkeits-Auswahl
            (<span class="glyphicon glyphicon-eye-open btn-success sichtbarkeit-beispiel"></span> und <span class="glyphicon glyphicon-eye-close btn-danger sichtbarkeit-beispiel"></span>)
            neben den Einträgen  kannst du einstellen, welche Daten für alle Mitglieder oder nur für dich und die
            Beauftragten der Mitgliederverwaltung sichtbar sind. Bei einigen Angaben kann die Sichtbarkeit nicht geändert werden. Weitere Informationen findest du in der Datenschutzerklärung (Link in der Navigation).
        </p>
        <?=form_row('Vorname(n) + Nachname', [
            [$vorname->input(disabled: !$this->check($isAdmin)), 5],
            [$nachname->input(disabled: !$this->check($isAdmin)), 5],
        ])?>
        <?=form_row('Straße + Hausnummer', [$strasse->input(uncover: $sichtbarkeit_strasse, placeholder: 'Straße + Hausnummer')])?>
        <?=form_row('ggf. Adresszusatz', [$adresszusatz->input(uncover: $sichtbarkeit_adresszusatz, placeholder: 'Adresszusatz')])?>
        <?=form_row('PLZ + Ort + Land', [
            [$plz->input(uncover: $sichtbarkeit_plz_ort, placeholder: 'PLZ'), 2],
            [$ort->input(uncover: $sichtbarkeit_plz_ort, placeholder: 'Ort'), 4],
            [$land->input(uncover: $sichtbarkeit_land, placeholder: 'Land'), 4],
        ])?>

        <script>
            let sichtbarkeit_plz_ort_recursion = false;
            document.querySelectorAll('[name=sichtbarkeit_plz_ort]').forEach(a => a.onchange = function () {
                if (sichtbarkeit_plz_ort_recursion) return;
                sichtbarkeit_plz_ort_recursion = true;
                const check = this.checked;
                document.querySelectorAll('[name=sichtbarkeit_plz_ort]').forEach(b => {
                    if (a == b) return;
                    $(b).bootstrapToggle(check ? 'on' : 'off');
                });
                sichtbarkeit_plz_ort_recursion = false;
            });
        </script>

        <?php $geburtstag ??= TemplateVariable::create('geburtstag', new DateTimeImmutable('0000-00-00')); ?>
        <?=form_row('Geburtsdatum', [$geburtstag->input(type: 'date', uncover: $sichtbarkeit_geburtstag, disabled: !$this->check($isAdmin))])?>
        <?=form_row('E-Mail' . (($this->check($email_auth_info)) ? ' (wird geändert)' : '') , [$email->input(type: 'email', placeholder: 'E-Mail', uncover: $sichtbarkeit_email)])?>
        <?=form_row('Telefon', [$telefon->input(type: 'tel', uncover: $sichtbarkeit_telefon)])?>
        <?=form_row('Beschäftigung', [$beschaeftigung->select([
            'Schueler' => 'Schüler:in',
            'Hochschulstudent' => 'Hochschulstudent:in',
            'Doktorand' => 'Doktorand:in',
            'Berufstaetig' => 'berufstätig',
            'Sonstiges' => 'sonstiges',
        ], uncover: $sichtbarkeit_beschaeftigung)])?>
    </div>

    <div class="tab-pane <?=$active_pane == 'uebermich' ? 'active' : ''?>" id="uebermich">
        <h3>Über mich</h3>
        <p>Teile mehr von dir mit, damit du im Netzwerk leichter gefunden wirst.</p>
        <?=form_row('ggf. Mensa-Mitgliedsnr.', [$mensa_nr->input(placeholder: 'Mensa-Mitgliedsnummer', uncover: $sichtbarkeit_mensa_nr)])?>
        <?=form_row('Titel', [[$titel->input(placeholder: 'Titel'), 2]])?>

        <h4>Kontaktdaten</h4>
        <?=form_row('Homepage', [$homepage->input(placeholder: 'Homepage')])?>

        <h4>Zweitwohnsitz <small>z.B. Adresse der Eltern</small></h4>
        <?=form_row('Straße + Hausnummer', [$strasse2->input(placeholder: 'Straße + Hausnummer')])?>
        <?=form_row('ggf. Adresszusatz', [$adresszusatz2->input(placeholder: 'ggf. Adresszusatz')])?>
        <?=form_row('PLZ + Ort + Land', [
            [$plz2->input(placeholder: 'PLZ'), 2],
            [$ort2->input(placeholder: 'Ort'), 4],
            [$land2->input(placeholder: 'Land'), 4],
        ])?>

        <h4>Sprachen, Hobbys, Interessen</h4>
        <?=form_row('Sprachen', [$sprachen->input(placeholder: 'Sprachen')])?>
        <?=form_row('Hobbys', [$hobbys->input(placeholder: 'Hobbys')])?>
        <?=form_row('Interessen', [$interessen->input(placeholder: 'Interessen')])?>
    </div>

    <div class="tab-pane <?=$active_pane == 'ausbildungberuf' ? 'active' : ''?>" id="ausbildungberuf">
        <h3>Ausbildung und Beruf</h3>

        <p>
            Angaben über deine Ausbildung und Beruf können anderen Mitgliedern helfen, Ansprechpartner/innen für Fragen zu ihrer eigenen Ausbildung zu finden. Dies ist einer der Kerngedanken des Netzwerks.
        </p>

        <?=form_row('Hochschultyp + Studienort', [
            [$unityp->input(placeholder: 'Hochschultyp (Universität, Fachhochschule, ...)', uncover: $sichtbarkeit_unityp), 5],
            [$studienort->input(placeholder: 'Studienort', uncover: $sichtbarkeit_studienort), 5],
        ])?>
        <?=form_row('Studienfach, Ausbildung + Schwerpunkt', [
            [$studienfach->input(placeholder: 'Studiengang, Ausbildung', uncover: $sichtbarkeit_studienfach), 5],
            [$schwerpunkt->input(placeholder: 'Schwerpunkt', uncover: $sichtbarkeit_schwerpunkt), 5],
        ])?>
        <?=form_row('ggf. Nebenfach', [$nebenfach->input(placeholder: 'Nebenfach', uncover: $sichtbarkeit_nebenfach)])?>
        <?=form_row('Abschluss', [$abschluss->input(placeholder: 'Abschluss', uncover: $sichtbarkeit_abschluss)])?>
        <?=form_row('ggf. Zweitstudium', [$zweitstudium->input(placeholder: 'Zweitstudium', uncover: $sichtbarkeit_zweitstudium)])?>
        <?=form_row('Hochschulaktivitäten', [$hochschulaktivitaeten->input(placeholder: 'Hochschulaktivitäten (Fachschaftsarbeit, ...)', uncover: $sichtbarkeit_hochschulaktivitaeten)])?>
        <?=form_row('Stipendien', [$stipendien->input(placeholder: 'Stipendien', uncover: $sichtbarkeit_stipendien)])?>
        <?=form_row('Auslandsaufenthalte', [$auslandsaufenthalte->input(placeholder: 'Auslandsaufenthalte', uncover: $sichtbarkeit_auslandsaufenthalte)])?>
        <?=form_row('Praktika, Fort- und Weiterbildungen', [$praktika->input(placeholder: 'Praktika, Fort- und Weiterbildungen', uncover: $sichtbarkeit_praktika)])?>
        <?=form_row('Beruf', [$beruf->input(placeholder: 'Beruf', uncover: $sichtbarkeit_beruf)])?>

    </div>

    <div class="tab-pane <?=$active_pane == 'profilbild' ? 'active' : ''?>" id="profilbild">
        <h3>Profilbild</h3>

        <p>Lade ein Profilbild hoch, damit andere eine Vorstellung haben, mit wem sie es zu tun haben. Das Profilbild ist für alle Mitglieder sichtbar.</p>

        <div class="form-group row">
            <label for="aktuellesBild" class="col-sm-2 col-form-label">Profilbild</label>
            <div class="col-sm-10 text-center">
                <img id="aktuellesBild" src="<?=$this->check($profilbild) ? ('/profilbilder/'.$profilbild) : ('/img/profilbild-default.png')?>" />
            </div>
        </div>

        <div class="form-group row">
            <label for="profilbild" class="col-sm-2 col-form-label">Bild ändern</label>
            <div class="col-sm-10">

                <div class="input-group">
                    <label class="input-group-btn">
                        <span class="btn btn-primary">
                            Datei auswählen &hellip; <input name="profilbild" type="file" style="display: none;">
                        </span>
                    </label>
                    <input type="text" class="form-control" readonly="readonly">
                </div>
            </div>
        </div>

        <?php if ($this->check($profilbild)): ?>
            <div class="form-group row">
                <label for="bildLoeschen" class="col-sm-2 col-form-label">Bild löschen</label>
                <div class="col-sm-10"><?=$bildLoeschen->box(label: 'Bild löschen')?></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="tab-pane <?=$active_pane == 'settings' ? 'active' : ''?>" id="settings">
        <h3>Netzwerk</h3>

        <p>
            Wie möchtest du im Netzwerk aktiv sein? Diese Angaben können für alle Mitglieder sichtbar sein.
        </p>

        <div class="row form-group">
            <div class="col-sm-6">
                <h4>Ich gebe Auskunft über</h4>
                <div class="checkbox"><?=$auskunft_studiengang->box(label: 'Studiengang')?></div>
                <div class="checkbox"><?=$auskunft_stipendien->box(label: 'Stipendien')?></div>
                <div class="checkbox"><?=$auskunft_auslandsaufenthalte->box(label: 'Auslandsaufenthalte')?></div>
                <div class="checkbox"><?=$auskunft_praktika->box(label: 'Praktika')?></div>
                <div class="checkbox"><?=$auskunft_beruf->box(label: 'Beruf')?></div>
                <div class="checkbox"><?=$mentoring->box(label: 'Ich bin prinzipiell bereit zu beruflichem Mentoring')?></div>
            </div>

            <div class="col-sm-6">
                <h4>Ich könnte bei folgenden Aufgaben helfen</h4>
                <div class="checkbox"><?=$aufgabe_ma->box(label: "Mithilfe bei der Organisation der MIND AKADEMIE")?></div>
                <div class="checkbox"><?=$aufgabe_orte->box(label: "Mithilfe bei der Suche nach Veranstaltungsorten")?></div>
                <div class="checkbox"><?=$aufgabe_vortrag->box(label: "einen Vortrag, ein Seminar oder einen Workshop anbieten")?></div>
                <div class="checkbox"><?=$aufgabe_koord->box(label: "eine Koordinations-Aufgabe, die man per Mail/Tel. von zu Hause erledigen kann")?></div>
                <div class="checkbox"><?=$aufgabe_graphisch->box(label: "eine graphisch-kreative Aufgabe")?></div>
                <div class="checkbox"><?=$aufgabe_computer->box(label: "eine Aufgabe, in der ich mein Computer-/IT-Wissen einbringen kann")?></div>
                <div class="checkbox"><?=$aufgabe_texte_schreiben->box(label: "Texte verfassen (z.B. für die Homepage oder den MHN-Newsletter)")?></div>
                <div class="checkbox"><?=$aufgabe_texte_lesen->box(label: "Texte durchlesen und kommentieren")?></div>
                <div class="checkbox"><?=$aufgabe_vermittlung->box(label: "Weitervermittlung von Kontakten")?></div>
                <div class="checkbox"><?=$aufgabe_ansprechpartner->box(label: "Ansprechpartner vor Ort (lokale Treffen organisieren, Plakate aufhängen)")?></div>
                <div class="checkbox"><?=$aufgabe_hilfe->box(label: "eine kleine, zeitlich begrenzte Aufgabe, wenn ihr dringend Hilfe braucht")?></div>
                <div class="checkbox"><?=$aufgabe_sonstiges->box(label: "Sonstiges: " . $aufgabe_sonstiges_beschreibung->input(placeholder: "Bitte spezifizieren"))?></div>
            </div>
        </div>
    </div>

    <div class="tab-pane <?=$active_pane == 'account' ? 'active' : ''?>" id="account">
            <h3>Mitgliedskonto</h3>

            <div class="row"><div class="col-sm-2">MHN-Mitgliedsnummer</div><div class="col-sm-10"><?=$id?></div></div>
            <div class="row"><div class="col-sm-2">Anmeldename</div><div class="col-sm-10"><?=$username?></div></div>
            <div class="row"><div class="col-sm-2">Aufnahmedatum</div><div class="col-sm-10"><?=$aufnahmedatum?->format('d.m.Y') ?? 'unbekannt'?></div></div>
            <div class="row"><div class="col-sm-2">Letzte Bearbeitung</div><div class="col-sm-10"><?=$db_modified?->format('d.m.Y H:i:s') ?? 'unbekannt'?> durch <?=$db_modified_user?->get('profilLink')->raw() ?? 'unbekannt'?></div></div>
            <div class="row"><div class="col-sm-2">Vereinseintritt</div><div class="col-sm-10"><?=$dateOfJoining?->format('d.m.Y') ?? 'kein Mitglied'?></div></div>
            <div class="row"><div class="col-sm-2">Datenschutzerklärungen</div><div class="col-sm-10"><a href="/user/<?=$username?>/agreements">Einwilligungen anzeigen / ändern</a></div></div>

            <div class="row">
                <div class="col-sm-2"><label for="resignPassword">Austritt erklären</label></div>
                <div class="col-sm-10">
                    <?php if ($this->check($isAdmin)): ?>
                        <?=$resign->box(
                                id: 'resignCheckbox', label: 'Das Mitglied hat seinen Austritt erklärt.',
                                onclick: self::htmlEscape('return confirm("Bist du ganz sicher, dass dieses Häkchen " + (!$("#resignCheckbox").get(0).checked ? "entfernt" : "gesetzt") + " werden soll?")'))
                        ?>
                        <?php if ($this->check($resignation)): ?>
                            <p>Die Austrittserklärung wurde am <?=$resignation->format('d.m.Y')?> gespeichert.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($this->check($resignation)): ?>
                            <p>Deine Austrittserklärung wurde am <?=$resignation->format('d.m.Y')?> gespeichert. Wenn du deinen Austritt zurücknehmen möchtest, wende dich bitte an den <a href="mailto:vorstand@mind-hochschul-netzwerk.de">Vorstand</a>. Der Austritt wird gemäß unserer Satzung zum Ende des Kalenderjahres wirksam.</p>
                        <?php else: ?>
                            <p>Mit einer Erklärung an den <a href="mailto:vorstand@mind-hochschul-netzwerk.de">Vorstand</a>, kannst du deine MHN-Mitgliedschaft beenden. Mit dem Ende deiner Mitgliedschaft werden wir deine persönlichen Daten aus der Mitgliederdatenbank löschen.</p>
                            <p class="resign__button"><button type="button" id="resign" name="resign" class="btn btn-danger">Austritt erklären</button></p>
                            <div class="resign__password hidden">
                                <p>Gib hier dein Passwort ein, wenn du deine Mitgliedschaft wirklich beenden möchtest, und klicke dann auf speichern.</p>
                                <div><input id="resignPassword" name="resignPassword" type="password" class="form-control" autocomplete="new-password" placeholder="Passwort"></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <h4>Passwort ändern</h4>
            <?php if (!$this->check($isAdmin) || $this->check($isSelf)): ?>
                <?=form_row('Altes Passwort', [$password->input(type: 'password', placeholder: 'Altes Passwort', autocomplete: 'current-password')])?>
            <?php endif; ?>
            <?=form_row('Neues Passwort', [$new_password->input(type: 'password', placeholder: 'Neues Passwort', autocomplete: 'new-password')])?>
            <?=form_row('Passwort wiederholen', [$new_password2->input(type: 'password', placeholder: 'Passwort wiederholen')])?>

            <?php if ($this->check($isAdmin)): ?>
                <h4>Mitgliederverwaltung</h4>

                <?php if ($this->check($isSuperAdmin)): ?>
                    <?=form_row('Gruppen ändern', [$groups->input(placeholder: 'Trennen durch Komma. Mögliche Werte siehe Menüpunkt „Mitgliederverwaltung”')])?>
                <?php else: ?>
                    <div class="row">
                    <div class="col-sm-2">Gruppen</div>
                    <div class="col-sm-10"><?=$groups?></div>
                </div>
                <?php endif; ?>

                <div class="form-group row">
                    <label class="col-sm-2 col-form-label">Mitglied löschen</label>
                    <div class="col-sm-10">
                        <?=$delete->box(id: 'delete',
                            onclick: self::htmlEscape('return confirm("Bist du ganz sicher?" + (!$("#delete").get(0).checked ? "" : " Daten werden unwiederbringlich gelöscht!"));'),
                            label: 'Die Daten werden SOFORT endgültig und unwiederbringlich gelöscht! Die Mitglieder der Mitgliederbetreuung werden informiert.')?>
                    </div>
                </div>
            <?php endif; ?>
    </div>
</div>

<div class="form-group row">
    <div class="col-sm-12">
        <button type="submit" class="btn btn-success">Speichern</button>
    </div>
</div>

</form>
    <script>
$(function() {
  // We can attach the `fileselect` event to all file inputs on the page
  $(document).on('change', ':file', function() {
    var input = $(this),
        label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
    input.trigger('fileselect', label);
  });

  // We can watch for our custom `fileselect` event like this
  $(document).ready( function() {
      $(':file').on('fileselect', function(event, label) {

          var input = $(this).parents('.input-group').find(':text');

          if( input.length ) {
              input.val(label);
          }
      });
  });
});

// vor dem Verlassen warnen, falls etwas geändert wurde
let changes = <?=$this->check($changes) ? 'true' : 'false'?>;
let saving = false;

$(document).on('change', 'input', (e) => {
    if (e.target.autocomplete != "current-password") {
        changes = true;
    }
});
document.getElementById("profile-form").addEventListener("submit", e => {
    saving = true;
});
window.addEventListener("beforeunload", e => {
    if (changes && !saving) {
        e.preventDefault();
        e.returnValue = "";
    }
});

document.getElementById("profile-form").addEventListener("submit", e => {
    let input1 = document.querySelector("[name=new_password]").value;
    let input2 = document.querySelector("[name=new_password2]").value;
    if (input1 !== input2) {
        let alert = document.getElementById("AlertWiederholungFalsch");
        alert.classList.remove("hide");
        alert.scrollIntoView();
        e.preventDefault();
    }
});

resignButton = document.querySelector("[type=button][name=resign]");
if (resignButton !== null) {
    resignButton.addEventListener("click", e => {
        e.preventDefault();
        document.querySelector(".resign__password").classList.remove("hidden");
        document.querySelector(".resign__button").classList.add("hidden");
    });
}

</script>