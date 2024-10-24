<?php
$this->extends('Layout/layout', [
    'title' => "Meine Mitgliedsdaten im MHN <small><span class='glyphicon glyphicon-user'></span> <a href='/user/$username'>Profil anzeigen</a></small>",
    'htmlTitle' => 'Mein Profil',
    'navId' => 'bearbeiten',
]);

function form_row($label, $inputs)
{
    $html = '';
    $for = null;
    $has_danger = '';

    foreach ($inputs as $id => $input) {
        $id = $input[0];
        $value = $input[1];
        $select = (!empty($input[2]) && $input[2] === 'select');
        $type = !empty($input[2]) ? "type='$input[2]'" : '';
        $cols = !empty($input[3]) ? $input[3] : floor(10 / count($inputs));

        $class = !empty($input['class']) ? $input['class'] : 'form-control';

        if (!empty($input['error'])) {
            $class .= ' form-control-danger';
            $has_danger = 'has-danger';
        }

        if (empty($input['disabled'])) {
            $name = "name='$id'";
            $disabled = '';
        } else {
            $name = '';
            $disabled = 'disabled="disabled"';
            $placeholder = '';
            if (!isset($input['title'])) {
                $input['title'] = 'Bitte wende dich an die Mitgliederbetreuung, wenn dieses Feld geändert werden muss.';
            }
        }

        $placeholder = !empty($input['placeholder']) ? $input['placeholder'] : $label;
        $title = !empty($input['title']) ? $input['title'] : $placeholder;
        $autocomplete = !empty($input['autocomplete']) ? "autocomplete='{$input['autocomplete']}'" : '';

        if ($select) {
            $tag = "<select id='input-$id' $name class='$class' $disabled title='$title'>\n";
            foreach ($input['options'] as $key => $text) {
                $tag .= "<option value='$key' ".(strtolower($value) === strtolower($key) ? 'selected="selected"' : '').">$text</option>\n";
            }
            $tag .= "</select>\n";
        } else {
            $tag = "<input id='input-$id' $name value='$value' $type class='$class' $disabled placeholder='$placeholder' title='$title' $autocomplete>\n";
        }


        if (!isset($input['sichtbarkeit'])) {
            $input['sichtbarkeit'] = true;
        }

        if (is_array($input['sichtbarkeit']) || is_bool($input['sichtbarkeit'])) {
            $name = '';
            $id = '';

            if (is_array($input['sichtbarkeit'])) {
                $name = "name='{$input['sichtbarkeit'][0]}'";
                $id = "id='{$input['sichtbarkeit'][0]}'";
                $checked = $input['sichtbarkeit'][1] ? 'checked="checked"' : '';
                $disabled = '';
            } else {
                $checked = $input['sichtbarkeit'] ? 'checked="checked"' : '';
                $disabled = 'disabled';
            }
            $tag = "<div class='input-group' data-tooltip='sichtbarkeit'>$tag
                <span class='input-group-addon'><input class='input-group-addon' $name $id data-height='32' data-width='50' data-toggle='toggle' data-onstyle='success' data-offstyle='danger' data-on='&lt;span class=&quot;glyphicon glyphicon-eye-open&quot;&gt;&lt;/span&gt;' data-off='&lt;span class=&quot;glyphicon glyphicon-eye-close&quot;&gt;&lt;/span&gt;' type='checkbox' $checked $disabled></span>
                    </div>";
        }

        $html .= "<div class='col-sm-$cols'>$tag</div>";

        if (!$for) {
            $for = "input-$id";
        }
    }

    if (!$label) {
        return $html;
    }

    return "<div class='form-group row $has_danger'>
        <label for='$id' class='col-sm-2 col-form-label'>$label</label>
        $html
    </div>\n";
}

if (empty($active_pane)) {
    $active_pane = 'basisdaten';
}

$changes = $error = $password_error = false;

// Alerts generieren

if (!empty($profilbild_format_unbekannt)) {
    $this->include('Layout/alert', [
        'alert_type' => 'danger',
        'alert_text' => 'Das Dateiformat des Profilbilds wurde nicht erkannt oder wird nicht unterstützt. Unterstützte Formate: JPEG, PNG.',
    ]);
    $active_pane = 'profilbild';
    $changes = $error = true;
}

if (!empty($profilbild_uploadfehler)) {
    $this->include('Layout/alert', [
        'alert_type' => 'danger',
        'alert_text' => 'Beim Upload des Profilbilds ist es leider zu einem unbekannten Fehler gekommen. Bitte wende dich an die Mitgliederbetreuung.',
    ]);
    $active_pane = 'profilbild';
    $changes = $error = true;
}

if (!empty($email_error)) {
    $this->include('Layout/alert', [
        'alert_type' => 'danger',
        'alert_text' => 'Die eigegebene E-Mail-Adresse ist ungültig. Die E-Mail-Adresse und wurde nicht gespeichert.',
    ]);
    $changes = $error = true;
} else {
    $email_error = false;
}

if (!empty($email_changed)) {
    $this->include('Layout/alert', [
        'alert_type' => 'success',
        'alert_text' => 'Deine E-Mail-Adresse wurde erfolgreich geändert.',
    ]);
}

$this->include('Layout/alert', [
    'alert_id' => 'AlertWiederholungFalsch',
    'alert_type' => 'danger',
    'alert_hide' => empty($new_password2_error),
    'alert_text' => 'Die Wiederholung stimmt nicht mit dem neuen Passwort überein.',
]);

if (!empty($new_password2_error)) {
    $active_pane = 'basisdaten';
    $password_error = $changes = $error = true;
}

if (!empty($old_password_error)) {
    $this->include('Layout/alert', [
        'alert_type' => 'danger',
        'alert_text' => 'Das alte Passwort ist falsch. Das Passwort wurde nicht geändert.',
    ]);
    $active_pane = 'basisdaten';
    $password_error = $changes = $error = true;
}

if (!empty($data_saved_info)) {
    $this->include('Layout/alert', [
        'alert_type' => 'success',
        'alert_text' => (!$error ? 'Deine Daten wurden geändert.' : 'Die anderen Änderungen wurden gespeichert.') . (!empty($email_auth_info) ? ' Bitte schau in dein E-Mail-Postfach, um die Änderung deiner E-Mail-Adresse abzuschließen.' : ''),
    ]);
}

if (!empty($errorMessage)) {
    $this->include('Layout/alert', [
        'alert_type' => 'warning',
        'alert_text' => $errorMessage,
    ]);
}
?>

<ul class="nav nav-tabs">
    <li <?=$active_pane === 'basisdaten' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#basisdaten">Basisdaten</a></li>
    <li <?=$active_pane === 'uebermich' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#uebermich">Über mich</a></li>
    <li <?=$active_pane === 'ausbildungberuf' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#ausbildungberuf">Ausbildung und Beruf</a></li>
    <li <?=$active_pane === 'profilbild' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#profilbild">Profilbild</a></li>
    <li <?=$active_pane === 'settings' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#settings">Netzwerk</a></li>
    <li <?=$active_pane === 'account' ? 'class="active"' : ''?> ><a data-toggle="tab" href="#account">MHN-Mitgliedskonto</a></li>
</ul>

<form enctype="multipart/form-data" method="post" id="profile-form" action="/user/<?=$username?>/update">

<div class="tab-content">
    <div class="tab-pane <?=$active_pane === 'basisdaten' ? 'active' : ''?>" id="basisdaten">
        <div class="pull-right"><input class='input-group-addon' id='sichtbarkeit_basisdaten' data-height='32' data-width='50' data-toggle='toggle' data-onstyle='success' data-offstyle='danger' data-on='&lt;span class=&quot;glyphicon glyphicon-eye-open&quot;&gt;&lt;/span&gt;' data-off='&lt;span class=&quot;glyphicon glyphicon-eye-close&quot;&gt;&lt;/span&gt;' type='checkbox'></div>

        <script>
            document.getElementById('sichtbarkeit_basisdaten').onchange = function () {
                onOrOff = this.checked ? 'on' : 'off';
                $('#sichtbarkeit_geburtstag').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_email').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_telefon').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_strasse').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_adresszusatz').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_plz_ort').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_land').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_beschaeftigung').bootstrapToggle(onOrOff);
            };
        </script>

        <h3>Basisdaten</h3>
        <p>
            Diese Basisdaten werden von allen Mitgliedern erhoben. Mit der Sichtbarkeits-Auswahl
            (<span class="glyphicon glyphicon-eye-open btn-success sichtbarkeit-beispiel"></span> und <span class="glyphicon glyphicon-eye-close btn-danger sichtbarkeit-beispiel"></span>)
            neben den Einträgen und neben der Überschrift kannst du einstellen, welche Daten für alle Mitglieder oder nur für dich und die
            Beauftragten der Mitgliederverwaltung sichtbar sind. Bei einigen Angaben kann die Sichtbarkeit nicht geändert werden. Weitere Informationen findest du in der Datenschutzerklärung (Link in der Navigation).
        </p>

        <?=form_row('Vorname(n) + Nachname', [
            ['vorname', $vorname, 'text', 5, 'disabled' => !$isAdmin, 'sichtbarkeit' => true],
            ['nachname', $nachname, 'text', 5, 'disabled' => !$isAdmin, 'sichtbarkeit' => true],
        ])?>

        <?=form_row('Straße + Hausnummer', [['strasse', $strasse, 'sichtbarkeit' => ['sichtbarkeit_strasse', $sichtbarkeit_strasse]]])?>
        <?=form_row('ggf. Adresszusatz', [['adresszusatz', $adresszusatz, 'sichtbarkeit' => ['sichtbarkeit_adresszusatz', $sichtbarkeit_adresszusatz]]])?>

        <?=form_row('PLZ + Ort + Land', [
            ['plz', $plz, 'text', 2, 'placeholder' => 'PLZ', 'sichtbarkeit' => ['sichtbarkeit_plz', $sichtbarkeit_plz_ort]],
            ['ort', $ort, 'text', 4, 'placeholder' => 'Ort', 'sichtbarkeit' => ['sichtbarkeit_plz_ort', $sichtbarkeit_plz_ort]],
            ['land', $land, 'text', 4, 'placeholder' => 'Land', 'sichtbarkeit' => ['sichtbarkeit_land', $sichtbarkeit_land]],
        ])?>

        <script>
            var sichtbarkeit_plz_ort_changed = false;
            document.getElementById('sichtbarkeit_plz').onchange = function () {
                if (sichtbarkeit_plz_ort_changed === false) {
                    sichtbarkeit_plz_ort_changed = true;
                    $('#sichtbarkeit_plz_ort').bootstrapToggle(this.checked ? 'on' : 'off');
                    sichtbarkeit_plz_ort_changed = false;
                }
            };
            document.getElementById('sichtbarkeit_plz_ort').onchange = function () {
                $('#sichtbarkeit_plz').bootstrapToggle(this.checked ? 'on' : 'off');
            };
        </script>

        <?=form_row('Geburtsdatum', [
            ['geburtstag', $geburtstag === null ? '0000-00-00' : $geburtstag->format('Y-m-d'), 'date', 'placeholder' => 'Geburtsdatum', 'disabled' => !$isAdmin, 'sichtbarkeit' => ['sichtbarkeit_geburtstag', $sichtbarkeit_geburtstag]],
        ])?>

        <?=form_row('E-Mail' . ((!empty($email_auth_info)) ? ' (wird geändert)' : '') , [['email', $email, 'email', 'error' => $email_error, 'sichtbarkeit' => ['sichtbarkeit_email', $sichtbarkeit_email]]])?>
        <?=form_row('Telefon', [['telefon', $telefon, 'tel', 'sichtbarkeit' => ['sichtbarkeit_telefon', $sichtbarkeit_telefon]]])?>

        <?=form_row('Beschäftigung', [['beschaeftigung', $beschaeftigung, 'select', 'options' => [
            'Schueler' => 'Schüler:in',
            'Hochschulstudent' => 'Hochschulstudent:in',
            'Doktorand' => 'Doktorand:in',
            'Berufstaetig' => 'berufstätig',
            'Sonstiges' => 'sonstiges',
        ], 'sichtbarkeit' => ['sichtbarkeit_beschaeftigung', $sichtbarkeit_beschaeftigung]]])?>
    </div>

    <div class="tab-pane <?=$active_pane === 'uebermich' ? 'active' : ''?>" id="uebermich">
        <h3>Über mich</h3>

        <p>
            Teile mehr von dir mit, damit du im Netzwerk leichter gefunden wirst.
        </p>

        <?=form_row('ggf. Mensa-Mitgliedsnr.', [['mensa_nr', $mensa_nr, 'placeholder' => 'Mensa-Mitgliedsnummer', 'sichtbarkeit' => ['sichtbarkeit_mensa_nr', $sichtbarkeit_mensa_nr]]])?>
        <?=form_row('Titel', [
            ['titel', $titel, 'text', 2, 'placeholder' => 'Titel'],
        ])?>

        <h4>Kontaktdaten</h4>

        <?=form_row('Homepage', [['homepage', $homepage]])?>

        <h4>Zweitwohnsitz <small>z.B. Adresse der Eltern</small></h4>

        <?=form_row('Straße + Hausnummer', [['strasse2', $strasse2]])?>
        <?=form_row('ggf. Adresszusatz', [['adresszusatz2', $adresszusatz2]])?>

        <?=form_row('PLZ + Ort + Land', [
            ['plz2', $plz2, 'text', 2, 'placeholder' => 'PLZ'],
            ['ort2', $ort2, 'text', 4, 'placeholder' => 'Ort'],
            ['land2', $land2, 'text', 4, 'placeholder' => 'Land'],
        ])?>

        <h4>Sprachen, Hobbys, Interessen</h4>

        <?=form_row('Sprachen', [['sprachen', $sprachen]])?>
        <?=form_row('Hobbys', [['hobbys', $hobbys]])?>
        <?=form_row('Interessen', [['interessen', $interessen]])?>
    </div>

    <div class="tab-pane <?=$active_pane === 'ausbildungberuf' ? 'active' : ''?>" id="ausbildungberuf">
        <div class="pull-right"><input class='input-group-addon' id='sichtbarkeit_ausbildung' data-height='32' data-width='50' data-toggle='toggle' data-onstyle='success' data-offstyle='danger' data-on='&lt;span class=&quot;glyphicon glyphicon-eye-open&quot;&gt;&lt;/span&gt;' data-off='&lt;span class=&quot;glyphicon glyphicon-eye-close&quot;&gt;&lt;/span&gt;' type='checkbox'></div>

        <script>
            document.getElementById('sichtbarkeit_ausbildung').onchange = function () {
                onOrOff = this.checked ? 'on' : 'off';
                $('#sichtbarkeit_unityp').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_studienort').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_studienfach').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_schwerpunkt').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_nebenfach').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_abschluss').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_zweitstudium').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_hochschulaktivitaeten').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_stipendien').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_auslandsaufenthalte').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_praktika').bootstrapToggle(onOrOff);
                $('#sichtbarkeit_beruf').bootstrapToggle(onOrOff);
            };
        </script>

        <h3>Ausbildung und Beruf</h3>

        <p>
            Angaben über deine Ausbildung und Beruf können anderen Mitgliedern helfen, Ansprechpartner/innen für Fragen zu ihrer eigenen Ausbildung zu finden. Dies ist einer der Kerngedanken des Netzwerks.
        </p>

        <?=form_row('Hochschultyp + Studienort', [
            ['unityp', $unityp, 'placeholder' => 'Hochschultyp (Universität, Fachhochschule, ...)', 'sichtbarkeit' => ['sichtbarkeit_unityp', $sichtbarkeit_unityp]],
            ['studienort', $studienort, 'placeholder' => 'Studienort', 'sichtbarkeit' => ['sichtbarkeit_studienort', $sichtbarkeit_studienort]],
        ])?>
        <?=form_row('Studienfach, Ausbildung + Schwerpunkt', [
            ['studienfach', $studienfach, 'placeholder' => 'Studiengang, Ausbildung', 'sichtbarkeit' => ['sichtbarkeit_studienfach', $sichtbarkeit_studienfach]],
            ['schwerpunkt', $schwerpunkt, 'placeholder' => 'Schwerpunkt', 'sichtbarkeit' => ['sichtbarkeit_schwerpunkt', $sichtbarkeit_schwerpunkt]],
        ])?>
        <?=form_row('ggf. Nebenfach', [['nebenfach', $nebenfach, 'placeholder' => 'Nebenfach', 'sichtbarkeit' => ['sichtbarkeit_nebenfach', $sichtbarkeit_nebenfach]]])?>
        <?=form_row('Abschluss', [['abschluss', $abschluss, 'sichtbarkeit' => ['sichtbarkeit_abschluss', $sichtbarkeit_abschluss]]])?>
        <?=form_row('ggf. Zweitstudium', [['zweitstudium', $zweitstudium,  'placeholder' => 'Zweitstudium', 'sichtbarkeit' => ['sichtbarkeit_zweitstudium', $sichtbarkeit_zweitstudium]]])?>
        <?=form_row('Hochschulaktivitäten', [['hochschulaktivitaeten', $hochschulaktivitaeten,  'placeholder' => 'Hochschulaktivitäten (Fachschaftsarbeit, ...)', 'sichtbarkeit' => ['sichtbarkeit_hochschulaktivitaeten', $sichtbarkeit_hochschulaktivitaeten]]])?>
        <?=form_row('Stipendien', [['stipendien', $stipendien, 'sichtbarkeit' => ['sichtbarkeit_stipendien', $sichtbarkeit_stipendien]]])?>
        <?=form_row('Auslandsaufenthalte', [['auslandsaufenthalte', $auslandsaufenthalte, 'sichtbarkeit' => ['sichtbarkeit_auslandsaufenthalte', $sichtbarkeit_auslandsaufenthalte]]])?>
        <?=form_row('Praktika, Fort- und Weiterbildungen', [['praktika', $praktika, 'sichtbarkeit' => ['sichtbarkeit_praktika', $sichtbarkeit_praktika]]])?>
        <?=form_row('Beruf', [['beruf', $beruf, 'sichtbarkeit' => ['sichtbarkeit_beruf', $sichtbarkeit_beruf]]])?>

    </div>

    <div class="tab-pane <?=$active_pane === 'profilbild' ? 'active' : ''?>" id="profilbild">
        <h3>Profilbild</h3>

        <p>Lade ein Profilbild hoch, damit andere eine Vorstellung haben, mit wem sie es zu tun haben. Das Profilbild ist für alle Mitglieder sichtbar.</p>

        <div class="form-group row">
            <label for="aktuellesBild" class="col-sm-2 col-form-label">Profilbild</label>
            <div class="col-sm-10 text-center">
                <img id="aktuellesBild" src="<?=$profilbild ? ('/profilbilder/'.$profilbild) : ('/img/profilbild-default.png')?>" />
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

        <?php if ($profilbild): ?>
            <div class="form-group row">
                <label for="bildLoeschen" class="col-sm-2 col-form-label">Bild löschen</label>
                <div class="col-sm-10"><label><input type="checkbox" name="bildLoeschen"> Bild löschen</label></div>
            </div>
        <?php endif; ?>

    </div>

    <div class="tab-pane <?=$active_pane === 'settings' ? 'active' : ''?>" id="settings">
        <h3>Netzwerk</h3>

        <p>
            Wie möchtest du im Netzwerk aktiv sein? Diese Angaben können für alle Mitglieder sichtbar sein.
        </p>

        <div class="row form-group">
            <div class="col-sm-6">
                <h4>Ich gebe Auskunft über</h4>
                <div class="checkbox"><label><input name="auskunft_studiengang" type="checkbox" <?=$auskunft_studiengang ? 'checked="checked"' : ''?>> Studiengang</label></div>
                <div class="checkbox"><label><input name="auskunft_stipendien" type="checkbox" <?=$auskunft_stipendien ? 'checked="checked"' : ''?>> Stipendien</label></div>
                <div class="checkbox"><label><input name="auskunft_auslandsaufenthalte" type="checkbox"  <?=$auskunft_auslandsaufenthalte ? 'checked="checked"' : ''?> > Auslandsaufenthalte</label></div>
                <div class="checkbox"><label><input name="auskunft_praktika" type="checkbox"  <?=$auskunft_praktika ? 'checked="checked"' : ''?> > Praktika</label></div>
                <div class="checkbox"><label><input name="auskunft_beruf" type="checkbox"  <?=$auskunft_beruf ? 'checked="checked"' : ''?> > Beruf</label></div>
                <div class="checkbox"><label><input name="mentoring" type="checkbox"  <?=$mentoring ? 'checked="checked"' : ''?> > Ich bin prinzipiell bereit zu beruflichem Mentoring</label></div>
            </div>

            <div class="col-sm-6">
                <h4>Ich könnte bei folgenden Aufgaben helfen</h4>
                <div class="checkbox"><label><input name="aufgabe_ma" type="checkbox"  <?=$aufgabe_ma ? 'checked="checked"' : ''?> > Mithilfe bei der Organisation der MIND AKADEMIE</label></div>
                <div class="checkbox"><label><input name="aufgabe_orte" type="checkbox"  <?=$aufgabe_orte ? 'checked="checked"' : ''?> > Mithilfe bei der Suche nach Veranstaltungsorten</label></div>
                <div class="checkbox"><label><input name="aufgabe_vortrag" type="checkbox"  <?=$aufgabe_vortrag ? 'checked="checked"' : ''?> > einen Vortrag, ein Seminar oder einen Workshop anbieten</label></div>
                <div class="checkbox"><label><input name="aufgabe_koord" type="checkbox"  <?=$aufgabe_koord ? 'checked="checked"' : ''?> > eine Koordinations-Aufgabe, die man per Mail/Tel. von zu Hause erledigen kann</label></div>
                <div class="checkbox"><label><input name="aufgabe_graphisch" type="checkbox"  <?=$aufgabe_graphisch ? 'checked="checked"' : ''?> > eine graphisch-kreative Aufgabe</label></div>
                <div class="checkbox"><label><input name="aufgabe_computer" type="checkbox"  <?=$aufgabe_computer ? 'checked="checked"' : ''?> > eine Aufgabe, in der ich mein Computer-/IT-Wissen einbringen kann</label></div>
                <div class="checkbox"><label><input name="aufgabe_texte_schreiben" type="checkbox"  <?=$aufgabe_texte_schreiben ? 'checked="checked"' : ''?> > Texte verfassen (z.B. für die Homepage oder den MHN-Newsletter)</label></div>
                <div class="checkbox"><label><input name="aufgabe_texte_lesen" type="checkbox"  <?=$aufgabe_texte_lesen ? 'checked="checked"' : ''?> > Texte durchlesen und kommentieren</label></div>
                <div class="checkbox"><label><input name="aufgabe_vermittlung" type="checkbox"  <?=$aufgabe_vermittlung ? 'checked="checked"' : ''?> > Weitervermittlung von Kontakten</label></div>
                <div class="checkbox"><label><input name="aufgabe_ansprechpartner" type="checkbox"  <?=$aufgabe_ansprechpartner ? 'checked="checked"' : ''?> > Ansprechpartner vor Ort (lokale Treffen organisieren, Plakate aufhängen)</label></div>
                <div class="checkbox"><label><input name="aufgabe_hilfe" type="checkbox"  <?=$aufgabe_hilfe ? 'checked="checked"' : ''?> > eine kleine, zeitlich begrenzte Aufgabe, wenn ihr dringend Hilfe braucht</label></div>
                <div class="checkbox"><label><input name="aufgabe_sonstiges" type="checkbox"  <?=$aufgabe_sonstiges ? 'checked="checked"' : ''?>> Sonstiges: <input type="text" name="aufgabe_sonstiges_beschreibung" placeholder="Bitte spezifizieren" value="<?=$aufgabe_sonstiges_beschreibung?>" class="form-control" ></label></div>
            </div>
        </div>
    </div>

    <div class="tab-pane <?=$active_pane === 'account' ? 'active' : ''?>" id="account">
            <h3>Mitgliedskonto</h3>

            <div class="row">
                <div class="col-sm-2">MHN-Mitgliedsnummer</div>
                <div class="col-sm-10"><?=$id?></div>
            </div>

            <div class="row">
                <div class="col-sm-2">Anmeldename</div>
                <div class="col-sm-10"><?=$username?></div>
            </div>

            <div class="row">
                <div class="col-sm-2">Aufnahmedatum</div>
                <div class="col-sm-10"><?=($aufnahmedatum !== null ? $aufnahmedatum->format('d.m.Y') : 'unbekannt')?></div>
            </div>

            <div class="row">
                <div class="col-sm-2">Letzte Bearbeitung</div>
                <div class="col-sm-10"><?= $db_modified === null ? 'unbekannt' : $db_modified->format('d.m.Y H:i:s') ?> durch <?= $db_modified_user === null ? 'unbekannt' : $db_modified_user->get('profilLink') ?></div>
            </div>

            <div class="row">
                <div class="col-sm-2">Vereinseintritt</div>
                <div class="col-sm-10"><?=($dateOfJoining !== null ? $dateOfJoining->format('d.m.Y') : 'kein Mitglied')?></div>
            </div>

            <div class="row">
                <div class="col-sm-2"><label for="resignPassword">Austritt erklären</label></div>
                <div class="col-sm-10">
                    <?php if ($isAdmin): ?>
                        <label><input name="resign" type="checkbox" id="resignCheckbox"
                        <?php if ($resignation): ?>checked<?php endif; ?>
                        onclick="return confirm(&quot;Bist du ganz sicher, dass dieses Häkchen &quot; + (!$(&quot;#resignCheckbox&quot;).get(0).checked ? &quot;entfernt&quot; : &quot;gesetzt&quot;) + &quot; werden soll?&quot;);"
                        >
                        Das Mitglied hat seinen Austritt erklärt.
                        </label>
                        <?php if ($resignation): ?>
                            <p>Die Austrittserklärung wurde am <?=$resignation->format('d.m.Y')?> gespeichert.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($resignation): ?>
                            <p>Deine Austrittserklärung wurde am <?=$resignation->format('d.m.Y')?> gespeichert. Wenn du deinen Austritt zurücknehmen möchtest, wende dich bitte an den <a href="mailto:vorstand@mind-hochschul-netzwerk.de">Vorstand</a>. Der Austritt wird gemäß unserer Satzung zum Ende des Kalenderjahres wirksam.</p>
                        <?php else: ?>
                            <p>Mit einer Erklärung an den <a href="mailto:vorstand@mind-hochschul-netzwerk.de">Vorstand</a>, kannst du deine MHN-Mitgliedschaft beenden. Mit dem Ende deiner Mitgliedschaft werden wir deine persönlichen Daten aus der Mitgliederdatenbank löschen.</p>
                            <p class="resign__button"><button type="button" id="resign" class="btn btn-danger">Austritt erklären</button></p>
                            <div class="resign__password hidden">
                                <p>Gib hier dein Passwort ein, wenn du deine Mitgliedschaft wirklich beenden möchtest, und klicke dann auf speichern.</p>
                                <div><input id="resignPassword" name="resignPassword" type="password" class="form-control" autocomplete="new-password" placeholder="Passwort"></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <h4>Passwort ändern</h4>
            <?php if (!$isAdmin || $isSelf): ?>
                <?=form_row('Altes Passwort', [['password', '', 'password', 'error' => $password_error, 'sichtbarkeit' => '', 'autocomplete' => 'current-password']])?>
            <?php endif; ?>
            <?=form_row('Neues Passwort', [['new_password', '', 'password', 'error' => $password_error, 'sichtbarkeit' => '', 'autocomplete' => 'new-password']])?>
            <?=form_row('Passwort wiederholen', [['new_password2', '', 'password', 'error' => $password_error, 'sichtbarkeit' => '']])?>

            <?php if ($isAdmin): ?>
                <h4>Mitgliederverwaltung</h4>

                <div class="row">
                    <div class="col-sm-2">Kenntnisnahme zur Datenverarbeitung (Aufnahmetool)</div>
                    <div class="col-sm-10"><?= ($kenntnisnahme_datenverarbeitung_aufnahme === null) ? 'nein' : ('zur Kenntnis genommen am ' .  $kenntnisnahme_datenverarbeitung_aufnahme->format('d.m.Y, H:i:s') . ' Uhr.')?></div>
                </div>
                <div class="row">
                    <div class="col-sm-2">Kenntnisnahme zur Datenverarbeitung (Aufnahmetool): Text</div>
                    <div class="col-sm-10"><textarea disabled class="small" style="width:100%;"><?=$kenntnisnahme_datenverarbeitung_aufnahme_text?></textarea></div>
                </div>
                <div class="row">
                    <div class="col-sm-2">Einwilligung zur Datenverarbeitung (Aufnahmetool)</div>
                    <div class="col-sm-10"><?= ($einwilligung_datenverarbeitung_aufnahme === null) ? 'nein' : ('eingewilligt am ' .  $einwilligung_datenverarbeitung_aufnahme->format('d.m.Y, H:i:s') . ' Uhr.')?></div>
                </div>
                <div class="row">
                    <div class="col-sm-2">Einwilligung zur Datenverarbeitung (Aufnahmetool): Text</div>
                    <div class="col-sm-10"><textarea disabled class="small" style="width:100%;"><?=$einwilligung_datenverarbeitung_aufnahme_text?></textarea></div>
                </div>

                <?php if ($isSuperAdmin): ?>
                    <?=form_row('Gruppen ändern', [['groups', implode(', ', $groups), 'placeholder' => 'Trennen durch Komma. Mögliche Werte siehe Menüpunkt „Mitgliederverwaltung”', 'sichtbarkeit' => false]])?>
                <?php endif; ?>

                <div class="form-group row">
                    <label class="col-sm-2 col-form-label">Mitglied löschen</label>
                    <div class="col-sm-10"><label><input name="delete" type="checkbox" id="delete"
                        onclick="return confirm(&quot;Bist du ganz sicher?&quot; + (!$(&quot;#delete&quot;).get(0).checked ? &quot;&quot; : &quot; Daten werden unwiederbringlich gelöscht!&quot;));"
                        >
                        </label>
                        Die Daten werden SOFORT endgültig und unwiederbringlich gelöscht! Die Mitglieder der Mitgliederbetreuung werden informiert.
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
let changes = <?=$changes ? 'true' : 'false'?>;
let saving = false;

$(document).on('change', 'input', function() {
    changes = true;
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
    let input1 = document.getElementById("input-new_password").value;
    let input2 = document.getElementById("input-new_password2").value;
    if (input1 !== input2) {
        let alert = document.getElementById("AlertWiederholungFalsch");
        alert.classList.remove("hide");
        alert.scrollIntoView();
        e.preventDefault();
    }
});

resignButton = document.getElementById("resign");
if (resignButton !== null) {
    resignButton.addEventListener("click", e => {
        e.preventDefault();
        document.querySelector(".resign__password").classList.remove("hidden");
        document.querySelector(".resign__button").classList.add("hidden");
    });
}
</script>
