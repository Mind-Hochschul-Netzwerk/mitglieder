<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
* Nimmt einen neuen Benutzer vom Aufnahmetool entgegen
*
* @author Henrik Gebauer <mensa@henrik-gebauer.de>
*/

use MHN\Mitglieder\Password;
use MHN\Mitglieder\Mitglied;

const no_output_buffering = true; // Mitteilung an tpl.inc.php, dass es sich um ein Backend-Skript handelt

require_once '../../lib/base.inc.php';

header('Content-Type: application/json');

ob_start();
register_shutdown_function(function () {
    meldung('fail');
});

function meldung(string $status, $uid = null, $token = null)
{
    static $gemeldet = false;
    if ($gemeldet) {
        return;
    }
    $gemeldet = true;

    $text = ob_get_contents();
    ob_end_clean();

    $msg = ['status' => $status];
    if ($uid) {
        $msg['aktivierungslink'] = 'https://mitglieder.mind-hochschul-netzwerk.de/aktivieren.php?token=' . $token;
    }
    if ($text) {
        $msg['text'] = $text;
    }

    echo json_encode($msg);

    exit;
}

ensure($_REQUEST['signature'], ENSURE_STRING) or die('signature missing');
ensure($_REQUEST['data'], ENSURE_STRING) or die('data missing');

// Signatur prüfen
$hash = md5($_REQUEST['data']);
$signature = md5(getenv('SECRET_AUFNAHME') . '-' . $hash);
if ($_REQUEST['signature'] !== $signature) {
    die('signature invalid');
}

$data = json_decode($_REQUEST['data']);

$id = Mitglied::getIdByEmail($data->user_email);
if ($id !== null) {
    die('Die E-Mail-Adresse wird bereits von einem anderen Mitglied verwendet (ID=' . $id . ')');
}

// neuen Benutzer mit neuem Passwort anlegen (beim ersten Login wird automatisch ein neues Passwort verlangt)
$m = Mitglied::neu();

$m->setEmail($data->user_email);

/* Felder, die nicht gesetzt werden (Default siehe Mitglied::)
     'aufgabe_sonstiges_beschreibung'=>'',
     'sichtbarkeit_*',
     'aufgabe_ma' => '',
     'aufgabe_graphisch' => '',
     'aufgabe_texte_lesen' => '',
     'aufgabe_vermittlung' => '',
     'aufgabe_sonstiges' => '',

*/

const map_strings = [
     'titel' => 'mhn_titel',
     'vorname' => 'mhn_vorname',
     'nachname' => 'mhn_nachname',
     'geschlecht' => 'mhn_geschlecht',
     'mensa_nr' => 'mhn_mensa_nr',
     'strasse' => 'mhn_ws_strasse',
     'adresszusatz' => 'mhn_ws_zusatz',
     'plz' => 'mhn_ws_plz',
     'ort' => 'mhn_ws_ort',
     'land' => 'mhn_ws_land',
     'strasse2' => 'mhn_zws_strasse',
     'adresszusatz2' => 'mhn_zws_zusatz',
     'plz2' => 'mhn_zws_plz',
     'ort2' => 'mhn_zws_ort',
     'land2' => 'mhn_zws_land',
     'telefon' => 'mhn_telefon',
     'mobil' => 'mhn_mobil',
     'homepage' => 'mhn_homepage',
     'sprachen' => 'mhn_sprachen',
     'hobbys' => 'mhn_hobbies',
     'interessen' => 'mhn_interessen',
     'beschaeftigung' => 'mhn_beschaeftigung',
     'studienort' => 'mhn_studienort',
     'studienfach' => 'mhn_studienfach',
     'unityp' => 'mhn_unityp',
     'schwerpunkt' => 'mhn_schwerpunkt',
     'nebenfach' => 'mhn_nebenfach',
     'abschluss' => 'mhn_abschluss',
     'zweitstudium' => 'mhn_zweitstudium',
     'hochschulaktivitaeten' => 'mhn_hochschulaktivitaet',
     'stipendien' => 'mhn_stipendien',
     'auslandsaufenthalte' => 'mhn_ausland',
     'praktika' => 'mhn_praktika',
     'beruf' => 'mhn_beruf',
     'kenntnisnahme_datenverarbeitung_aufnahme' => 'kenntnisnahme_datenverarbeitung',
     'kenntnisnahme_datenverarbeitung_aufnahme_text' => 'kenntnisnahme_datenverarbeitung_text',
     'einwilligung_datenverarbeitung_aufnahme' => 'einwilligung_datenverarbeitung',
     'einwilligung_datenverarbeitung_aufnahme_text' => 'einwilligung_datenverarbeitung_text',
    ];

const map_bool = [
     'auskunft_studiengang' => 'mhn_auskunft_studiengang',
     'auskunft_stipendien' => 'mhn_auskunft_stipendien',
     'auskunft_auslandsaufenthalte' => 'mhn_auskunft_ausland',
     'auskunft_praktika' => 'mhn_auskunft_praktika',
     'auskunft_beruf' => 'mhn_auskunft_beruf',
     'mentoring' => 'mhn_mentoring',
     'aufgabe_orte' => 'mhn_aufgabe_orte',
     'aufgabe_vortrag' => 'mhn_aufgabe_vortrag',
     'aufgabe_koord' => 'mhn_aufgabe_koord',
     'aufgabe_computer' => 'mhn_aufgabe_computer',
     'aufgabe_texte_schreiben' => 'mhn_aufgabe_texte_schreiben',
     'aufgabe_ansprechpartner' => 'mhn_aufgabe_ansprechpartner',
     'aufgabe_hilfe' => 'mhn_aufgabe_hilfe',
];

foreach (map_strings as $key_neu => $key_alt) {
    if (isset($data->$key_alt)) {
        $m->set($key_neu, $data->$key_alt);
    }
}

foreach (map_bool as $key_neu => $key_alt) {
    if (!isset($data->$key_alt)) {
        $m->set($key_neu, false);
    } else {
        $m->set($key_neu, $data->$key_alt === 'j');
    }
}

if (!empty($data->mhn_ws_hausnr)) {
    $m->set('strasse', $m->get('strasse') . ' ' . $data->mhn_ws_hausnr);
}

if (!empty($data->mhn_zws_hausnr)) {
    $m->set('strasse2', $m->get('strasse2') . ' ' . $data->mhn_zws_hausnr);
}

if (isset($data->mhn_geburtstag)) {
    $m->set('geburtstag', $data->mhn_geburtstag);
}

// Alles klar!
$m->save();

meldung('success', $m->get('id'), $m->get('new_email_token'));

