<?php

use App\Service\TemplateVariable;

$this->extends('Layout/layout', [
    'navid' => 'suche',
]);

// gibt die Klasse "unsichtbar" zurück, falls die Sichtbarkeit eingeschränkt ist, und der Benutzer es nur wegen des Rechts mvread sehen kann
function sichtbarkeit(TemplateVariable|bool $sichtbarkeit)
{
    if ($sichtbarkeit instanceof TemplateVariable) {
        $sichtbarkeit = $sichtbarkeit->isTrue();
    }
    return $sichtbarkeit ? '' : 'unsichtbar';
}
function checkbox(TemplateVariable $checked)
{
    return $checked->isTrue() ? '<span class="checkbox">☑</span>' : '<span class="checkbox">☐</span>';
}
function row(string $label, ?TemplateVariable $value, TemplateVariable|bool $sichtbarkeit = true)
{
    if ($value === null || $value->isEmpty()) {
        return '';
    }
    return '<div class="row ' . sichtbarkeit($sichtbarkeit) . '"><div class="col-xs-6">' . $label . '</div><div class="col-xs-6">' . $value . "</div></div>\n";
}
?>

<?php if ($this->check($profilbild)): ?>
<div class="profilbild pull-right">
        <img src="/profilbilder/<?=$profilbild?>" alt="Profilbild" />
    </div>
<?php endif; ?>

    <div class="row">
        <div class="col-sm-6">
            <h4>Mitgliedschaft</h4>
            <?=row('Mitgliedsnummer', $id)?>
            <div class="row">
                <div class="col-xs-6">Mitglied seit</div>
                <div class="col-xs-6">
                        <?=$aufnahmedatum?->format('d.m.Y') ?? 'unbekannt'?>
                </div>
            </div>

            <?php if ($this->check($mensa_nr)): ?>
                <div class="row <?=sichtbarkeit($sichtbarkeit_mensa_nr)?>">
                    <div class="col-xs-6">Mensa-Mitgliedsnummer:</div>
                    <div class="col-xs-6"><?=$mensa_nr?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($this->check($geburtstag)): ?>
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

        <?php if ("$sprachen$hobbys$interessen" !== ''): ?>
        <div class="col-sm-6">
            <h4>Interessen</h4>
            <?=row('Sprachen', $sprachen)?>
            <?=row('Hobbys', $hobbys)?>
            <?=row('Interessen', $interessen)?>
        </div>
        <?php endif; ?>
    </div>

    <div class="row">
        <?php if ("$email$telefon$homepage"): ?>
            <div class="col-sm-3">
                <h4>Kontaktdaten</h4>
                <?php if ("$email"): ?><p class="<?=sichtbarkeit($sichtbarkeit_email)?>"><span class="glyphicon glyphicon-at"></span> <a href="mailto:<?=$email?>"><?=$email?></a></p><?php endif; ?>
                <?php if ("$telefon"): ?><p class="<?=sichtbarkeit($sichtbarkeit_telefon)?>"><span class="glyphicon glyphicon-earphone"></span> <?=$telefon?></p><?php endif; ?>
                <?php if ("$homepage"): ?><p><span class="glyphicon glyphicon-globe"></span> <a href="<?=$homepage?>"><?=$homepage?></a></p><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ("$strasse$adresszusatz$plz$ort$land"): ?>
            <div class="col-sm-3">
                <h4>Adresse</h4>
                <address>
                     <?php if ("$strasse"): ?><span class='<?=sichtbarkeit($sichtbarkeit_strasse)?>'><?=$strasse?></span><br><?php endif; ?>
                     <?php if ("$adresszusatz"): ?><span class='<?=sichtbarkeit($sichtbarkeit_adresszusatz)?>'><?=$adresszusatz?></span><br><?php endif; ?>
                     <?php if ("$plz$ort"): ?><span class='<?=sichtbarkeit($sichtbarkeit_plz_ort)?>'><?=trim("$plz $ort")?></span><br><?php endif; ?>
                     <?php if ("$land"): ?><span class='<?=sichtbarkeit($sichtbarkeit_land)?>'><?=$land?></span><br><?php endif; ?>
                </address>
            </div>
        <?php endif; ?>

        <?php if ("$strasse2$adresszusatz2$plz2$ort2$land2"): ?>
            <div class="col-sm-3">
                <h4>Zweitwohnsitz</h4>
                <address>
                     <?php if ("$strasse2"): ?><?=$strasse2?><br><?php endif; ?>
                     <?php if ("$adresszusatz2"): ?><?=$adresszusatz2?><br><?php endif; ?>
                     <?php if ("$plz2$ort2"): ?><?="$plz2 $ort2"?><br><?php endif; ?>
                     <?php if ("$land2"): ?><?=$land2?><br><?php endif; ?>
                </address>
            </div>
        <?php endif; ?>

    </div> <!-- /row -->

    <?php if ("$beschaeftigung$unityp$studienort$studienfach$schwerpunkt$nebenfach$abschluss$zweitstudium$hochschulaktivitaeten$stipendien$auslandsaufenthalte$praktika$beruf"): ?>
        <div class="row">
            <div class="col-sm-6">
                <h4>Angaben zu Ausbildung und Beruf</h4>
                <?php if ("$beschaeftigung"): ?>
                    <div class="row <?=sichtbarkeit($sichtbarkeit_beschaeftigung)?>">
                        <div class="col-xs-6">Beschäftigung</div>
                        <div class="col-xs-6">
                                <?php
                                    switch ("$beschaeftigung") {
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
                <?php endif; ?>
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
                <div><?=$vorname?> ist <?=$mentoring->isTrue() ? 'prinzipiell' : 'nicht'?> bereit zu beruflichem Mentoring.</div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row">

    <div class="col-sm-6">
        <h4><?=$vorname?> könnte bei folgenden Aufgaben helfen</h4>
        <ul>
            <?php if ($aufgabe_ma->isTrue()): ?><li>Mithilfe bei der Organisation der Mind-Akademie</li><?php endif; ?>
            <?php if ($aufgabe_ma->isTrue()): ?><li>Mithilfe bei der Organisation der MIND AKADEMIE</li><?php endif; ?>
            <?php if ($aufgabe_orte->isTrue()): ?><li>Mithilfe bei der Suche nach Veranstaltungsorten</li><?php endif; ?>
            <?php if ($aufgabe_vortrag->isTrue()): ?><li>einen Vortrag, ein Seminar oder einen Workshop anbieten</li><?php endif; ?>
            <?php if ($aufgabe_koord->isTrue()): ?><li>eine Koordinations-Aufgabe, die man per Mail/Tel. von zu Hause erledigen kann</li><?php endif; ?>
            <?php if ($aufgabe_graphisch->isTrue()): ?><li>eine graphisch-kreative Aufgabe</li><?php endif; ?>
            <?php if ($aufgabe_computer->isTrue()): ?><li>eine Aufgabe, in der ich mein Computer-/IT-Wissen einbringen kann</li><?php endif; ?>
            <?php if ($aufgabe_texte_schreiben->isTrue()): ?><li>Texte verfassen (z.B. für die Homepage oder den MHN-Newsletter)</li><?php endif; ?>
            <?php if ($aufgabe_texte_lesen->isTrue()): ?><li>Texte durchlesen und kommentieren</li><?php endif; ?>
            <?php if ($aufgabe_vermittlung->isTrue()): ?><li>Weitervermittlung von Kontakten</li><?php endif; ?>
            <?php if ($aufgabe_ansprechpartner->isTrue()): ?><li>Ansprechpartner vor Ort (lokale Treffen organisieren, Plakate aufhängen)</li><?php endif; ?>
            <?php if ($aufgabe_hilfe->isTrue()): ?><li>eine kleine, zeitlich begrenzte Aufgabe, wenn ihr dringend Hilfe braucht</li><?php endif; ?>
            <?php if ($aufgabe_sonstiges->isTrue()): ?><li>Sonstiges, und zwar: „<?=$aufgabe_sonstiges_beschreibung?>”</li><?php endif; ?>
        </ul>
    </div>

</div>
