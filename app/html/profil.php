<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Profil anzeigen
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Tpl;

require_once '../lib/base.inc.php';

Auth::intern();

if (ensure($_GET['id'], ENSURE_INT_GT, 0)) {
    $id = $_GET['id'];
} elseif (ensure($_GET['username'], ENSURE_STRING)) {
    $id = Mitglied::getIdByUsername($_GET['username']);
} else {
    $id = Auth::getUID();
}

$m = Mitglied::lade($id, Auth::hatRecht('mvread'));

if ($m === null) {
    die('ID bzw. Benutzername ungültig');
}

$db_modified = $m->get('db_modified');

Tpl::set('htmlTitle', $m->get('fullName'));
Tpl::set('title', $m->get('fullName')
    . ' <small>Stand: '. ($db_modified === null ? 'unbekannt' : $m->get('db_modified')->format('d.m.Y')) . '</small>'
    . ((Auth::ist($m->get('id')) or Auth::hatRecht('mvedit'))
        ? ' <small><a href="bearbeiten.php?id='.$m->get('id').'"><span class="glyphicon glyphicon-pencil"></span> Daten bearbeiten</a></small>'
        : ''), false);
Tpl::set('navId', 'suche');
Tpl::sendHead();

$mvread = Auth::hatRecht('mvread');
Tpl::set('mvread', $mvread);
Tpl::set('fullName', $m->get('fullName'));

// generell: alle Daten kopieren
foreach (array_keys(Mitglied::felder) as $feld) {
    Tpl::set($feld, $m->get($feld));
}

// Dann die sichtgeschützten Felder gesondert behandeln, damit das Template möglichst frei von Logik bleiben kann
Tpl::set('geschlecht', (!$m->get('sichtbarkeit_geschlecht') && !$mvread) ? 'u' : $m->get('geschlecht'));
Tpl::set('email', (!$m->get('sichtbarkeit_email') && !$mvread) ? '' : $m->get('email'));
Tpl::set('geburtstag', (!$m->get('sichtbarkeit_geburtstag') && !$mvread) ? '' : $m->get('geburtstag'));
Tpl::set('mensa_nr', (!$m->get('sichtbarkeit_mensa_nr') && !$mvread) ? '' : $m->get('mensa_nr'));
Tpl::set('strasse', (!$m->get('sichtbarkeit_strasse') && !$mvread) ? '' : $m->get('strasse'));
Tpl::set('adresszusatz', (!$m->get('sichtbarkeit_adresszusatz') && !$mvread) ? '' : $m->get('adresszusatz'));
Tpl::set('plz', (!$m->get('sichtbarkeit_plz_ort') && !$mvread) ? '' : $m->get('plz'));
Tpl::set('ort', (!$m->get('sichtbarkeit_plz_ort') && !$mvread) ? '' : $m->get('ort'));
Tpl::set('land', (!$m->get('sichtbarkeit_land') && !$mvread) ? '' : $m->get('land'));
Tpl::set('telefon', (!$m->get('sichtbarkeit_telefon') && !$mvread) ? '' : $m->get('telefon'));
Tpl::set('mobil', (!$m->get('sichtbarkeit_mobil') && !$mvread) ? '' : $m->get('mobil'));
Tpl::set('beschaeftigung', (!$m->get('sichtbarkeit_beschaeftigung') && !$mvread) ? '' : $m->get('beschaeftigung'));
Tpl::set('studienort', (!$m->get('sichtbarkeit_studienort') && !$mvread) ? '' : $m->get('studienort'));
Tpl::set('studienfach', (!$m->get('sichtbarkeit_studienfach') && !$mvread) ? '' : $m->get('studienfach'));
Tpl::set('unityp', (!$m->get('sichtbarkeit_unityp') && !$mvread) ? '' : $m->get('unityp'));
Tpl::set('schwerpunkt', (!$m->get('sichtbarkeit_schwerpunkt') && !$mvread) ? '' : $m->get('schwerpunkt'));
Tpl::set('nebenfach', (!$m->get('sichtbarkeit_nebenfach') && !$mvread) ? '' : $m->get('nebenfach'));
Tpl::set('abschluss', (!$m->get('sichtbarkeit_abschluss') && !$mvread) ? '' : $m->get('abschluss'));
Tpl::set('zweitstudium', (!$m->get('sichtbarkeit_zweitstudium') && !$mvread) ? '' : $m->get('zweitstudium'));
Tpl::set('hochschulaktivitaeten', (!$m->get('sichtbarkeit_hochschulaktivitaeten') && !$mvread) ? '' : $m->get('hochschulaktivitaeten'));
Tpl::set('stipendien', (!$m->get('sichtbarkeit_stipendien') && !$mvread) ? '' : $m->get('stipendien'));
Tpl::set('auslandsaufenthalte', (!$m->get('sichtbarkeit_auslandsaufenthalte') && !$mvread) ? '' : $m->get('auslandsaufenthalte'));
Tpl::set('praktika', (!$m->get('sichtbarkeit_praktika') && !$mvread) ? '' : $m->get('praktika'));
Tpl::set('beruf', (!$m->get('sichtbarkeit_beruf') && !$mvread) ? '' : $m->get('beruf'));

// Überprüfen, ob die Homepage das korrekte Format hat. ggf. http:// ergänzen
$homepage = $m->get('homepage');
if (!preg_match('=^https?://=i', $homepage)) {
    $homepage = 'http://' . $homepage;
}
if (!preg_match('=^https?://(?P<user>[^@]*@)?(?P<host>[\w\.0-9-]+)(?P<port>:[0-9]+)?(?<query>/.*)?$=i', $homepage)) {
    $homepage = '';
}
Tpl::set('homepage', $homepage);

Tpl::render('MitgliedController/profil');

Tpl::submit();
