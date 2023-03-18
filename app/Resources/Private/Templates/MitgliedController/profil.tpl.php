<?php declare(strict_types=1); namespace MHN\Mitglieder; ?>
<?php
// gibt die Klasse "unsichtbar" zurück, falls die Sichtbarkeit eingeschränkt ist, und der Benutzer es nur wegen dem Recht mvread sehen kann
function sichtbarkeit($sichtbarkeit)
{
    return $sichtbarkeit ? '' : 'unsichtbar';
}
function checkbox($checked)
{
    return $checked ? '<span class="checkbox">☑</span>' : '<span class="checkbox">☐</span>';
}
function row($label, $value, $sichtbarkeit = true)
{
    if (!$value) {
        return '';
    }
    return '<div class="row ' . sichtbarkeit($sichtbarkeit) . '"><div class="col-xs-6">' . $label . '</div><div class="col-xs-6">' . $value . "</div></div>\n";
}
?>

<?php if ($profilbild): ?>
<div class="profilbild pull-right">
        <img src="profilbilder/<?=$profilbild?>" alt="Profilbild" />
    </div>
<?php endif; ?>

    <div class="row">
        <div class="col-sm-6">
            <h4>Mitgliedschaft</h4>
            <?=row('Mitgliedsnummer', $id)?>
            <div class="row">
                <div class="col-xs-6">Mitglied seit</div>
                <div class="col-xs-6">
                        <?= $aufnahmedatum === null ? 'unbekannt' : $aufnahmedatum->format('d.m.Y') ?>
                </div>
            </div>

            <?php if ($mensa_nr): ?>
                <div class="row <?=sichtbarkeit($sichtbarkeit_mensa_nr)?>">
                    <div class="col-xs-6">Mensa-Mitgliedsnummer:</div>
                    <div class="col-xs-6"><?=$mensa_nr?></div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($geburtstag): ?>
            <div class="col-sm-6">
                <h4>Persönliche Daten</h4>

                <div class="row <?=sichtbarkeit($sichtbarkeit_geburtstag)?>">
                    <div class="col-xs-6">Geburtsdatum</div>
                    <div class="col-xs-6">
                        <?=$geburtstag->format('d.m.Y')?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($sprachen or $hobbys or $interessen): ?>
        <div class="col-sm-6">
            <h4>Interessen</h4>
            <?=row('Sprachen', $sprachen)?>
            <?=row('Hobbys', $hobbys)?>
            <?=row('Interessen', $interessen)?>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <?php if ($email or $telefon or $mobil or $homepage): ?>
            <div class="col-sm-3">
                <h4>Kontaktdaten</h4>
                <?php if ($email): ?><p class="<?=sichtbarkeit($sichtbarkeit_email)?>"><span class="glyphicon glyphicon-at"></span> <a href="mailto:<?=$email?>"><?=$email?></a></p><?php endif; ?>
                <?php if ($telefon): ?><p class="<?=sichtbarkeit($sichtbarkeit_telefon)?>"><span class="glyphicon glyphicon-earphone"></span> <?=$telefon?></p><?php endif; ?>
                <?php if ($mobil): ?><p class="<?=sichtbarkeit($sichtbarkeit_mobil)?>"><span class="glyphicon glyphicon-phone"></span> <?=$mobil?></p><?php endif; ?>
                <?php if ($homepage): ?><p><span class="glyphicon glyphicon-globe"></span> <a href="<?=$homepage?>"><?=$homepage?></a></p><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($strasse or $adresszusatz or $plz or $ort or $land): ?>
            <div class="col-sm-3">
                <h4>Adresse</h4>
                <address>
                     <?php if ($strasse): ?><span class='<?=sichtbarkeit($sichtbarkeit_strasse)?>'><?=$strasse?></span><br><?php endif; ?>
                     <?php if ($adresszusatz): ?><span class='<?=sichtbarkeit($sichtbarkeit_adresszusatz)?>'><?=$adresszusatz?></span><br><?php endif; ?>
                     <?php if ($plz or $ort): ?><span class='<?=sichtbarkeit($sichtbarkeit_plz_ort)?>'><?=trim("$plz $ort")?></span><br><?php endif; ?>
                     <?php if ($land): ?><span class='<?=sichtbarkeit($sichtbarkeit_land)?>'><?=$land?></span><br><?php endif; ?>
                </address>
            </div>
        <?php endif; ?>

        <?php if ($strasse2 or $adresszusatz2 or $plz2 or $ort2 or $land2): ?>
            <div class="col-sm-3">
                <h4>Zweitwohnsitz</h4>
                <address>
                     <?php if ($strasse2): ?><?=$strasse2?><br><?php endif; ?>
                     <?php if ($adresszusatz2): ?><?=$adresszusatz2?><br><?php endif; ?>
                     <?php if ($plz2 or $ort2): ?><?="$plz2 $ort2"?><br><?php endif; ?>
                     <?php if ($land2): ?><?=$land2?><br><?php endif; ?>
                </address>
            </div>
        <?php endif; ?>

    </div> <!-- /row -->

    <?php if ($beschaeftigung or $unityp or $studienort or $studienfach or $schwerpunkt or $nebenfach or $abschluss or $zweitstudium or $hochschulaktivitaeten or $stipendien or $auslandsaufenthalte or $praktika or $beruf): ?>
        <div class="row">
            <div class="col-sm-6">
                <h4>Angaben zu Ausbildung und Beruf</h4>
                <div class="row <?=sichtbarkeit($sichtbarkeit_beschaeftigung)?>">
                    <div class="col-xs-6">Beschäftigung</div>
                    <div class="col-xs-6">
                            <?php
                                switch ($beschaeftigung) {
                                case 'Schueler':
                                    echo 'Schüler:in';
                                    break;
                                case 'Hochschulstudent':
                                    echo 'Hochschulstudent:in';
                                    break;
                                case 'Doktorand':
                                    echo 'Doktorand:in';
                                    break;
                                case 'Berufstaetig':
                                    echo 'berufstätig';
                                    break;
                                case 'Sonstiges':
                                    echo 'sonstiges';
                                    break;
                                default:
                                    echo 'unbekannt';  // andere Einträge werden von bearbeiten.php aber eigentlich gar nicht erst durchgelassen
                                    break;
                                }
                            ?>
                    </div>
                </div>
                <?=row('Hochschultyp', $unityp, $sichtbarkeit_unityp)?>
                <?=row('Studienort', $studienort, $sichtbarkeit_studienort)?>
                <?=row('Studiengang, Ausbildung', $studienfach, $sichtbarkeit_studienfach)?>
                <?=row('Schwerpunkt', $schwerpunkt, $sichtbarkeit_schwerpunkt)?>
                <?=row('Nebenfach', $nebenfach, $sichtbarkeit_nebenfach)?>
                <?=row('Abschluss', $abschluss, $sichtbarkeit_abschluss)?>
                <?=row('Zweitstudium', $zweitstudium, $sichtbarkeit_zweitstudium)?>
                <?=row('Ehrenamtliches Engagement', $hochschulaktivitaeten, $sichtbarkeit_hochschulaktivitaeten)?>
                <?=row('Stipendien', $stipendien, $sichtbarkeit_stipendien)?>
                <?=row('Auslandsaufenthalte', $auslandsaufenthalte, $sichtbarkeit_auslandsaufenthalte)?>
                <?=row('Praktika, Fort- und Weiterbildung', $praktika, $sichtbarkeit_praktika)?>
                <?=row('Beruf', $beruf, $sichtbarkeit_beruf)?>
            </div>
            <div class="col-sm-6">
                <h4><?=$vorname?> gibt Auskunft über</h4>
                <div class="profil-checkbox"><?=checkbox($auskunft_studiengang)?> Studiengang, Ausbildung</div>
                <div class="profil-checkbox"><?=checkbox($auskunft_stipendien)?> Stipendien</div>
                <div class="profil-checkbox"><?=checkbox($auskunft_auslandsaufenthalte)?> Auslandsaufenthalte</div>
                <div class="profil-checkbox"><?=checkbox($auskunft_praktika)?> Praktika, Fort- und Weiterbildung</div>
                <div class="profil-checkbox"><?=checkbox($auskunft_beruf)?> Beruf</div>
                <div><?=$vorname?> ist <?=$mentoring ? 'prinzipiell' : 'nicht'?> bereit zu beruflichem Mentoring.</div>
            </div>
        </div>
    <?php endif; ?>

<?php if ($mvread): ?>
    <div class="row">
        <div class="col-sm-6">
            <h4 class="unsichtbar">Mitgliederverwaltung</h4>
            <div class="row">
                <div class="col-xs-6">Letzter Login</div>
                <div class="col-xs-6"><?= $last_login === null ? 'nie' : $last_login->format('d.m.Y') ?></div>
            </div>
        </div>
        <div class="col-sm-6">
            <h4 class="unsichtbar">Ich könnte bei folgenden Aufgaben helfen</h4>
            <div class="profil-checkbox"><?=checkbox($aufgabe_ma)?> Mithilfe bei der Organisation der MIND AKADEMIE</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_orte)?> Mithilfe bei der Suche nach Veranstaltungsorten</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_vortrag)?> einen Vortrag, ein Seminar oder einen Workshop anbieten</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_koord)?> eine Koordinations-Aufgabe, die man per Mail/Tel. von zu Hause erledigen kann</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_graphisch)?> eine graphisch-kreative Aufgabe</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_computer)?> eine Aufgabe, in der ich mein Computer-/IT-Wissen einbringen kann</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_texte_schreiben)?> Texte verfassen (z.B. für die Homepage oder den MHN-Newsletter)</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_texte_lesen)?> Texte durchlesen und kommentieren</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_vermittlung)?> Weitervermittlung von Kontakten</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_ansprechpartner)?> Ansprechpartner vor Ort (lokale Treffen organisieren, Plakate aufhängen)</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_hilfe)?> eine kleine, zeitlich begrenzte Aufgabe, wenn ihr dringend Hilfe braucht</div>
            <div class="profil-checkbox"><?=checkbox($aufgabe_sonstiges)?> Sonstiges, und zwar: „<?=$aufgabe_sonstiges_beschreibung?>”</div>
        </div>
    </div>
<?php endif; ?>
