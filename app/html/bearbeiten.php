<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Mitgliederdaten bearbeiten
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\DB;
use MHN\Mitglieder\Mitglied;
use MHN\Mitglieder\Tpl;
use MHN\Mitglieder\Domain\Repository\ChangeLog;
use MHN\Mitglieder\Domain\Model\ChangeLogEntry;
use \Hengeb\Token\Token;
use MHN\Mitglieder\Service\EmailService;

// Liste der vom Mitglied änderbaren Strings, deren Werte nicht geprüft werden
const bearbeiten_strings_ungeprueft = ['titel', 'mensa_nr', 'strasse', 'adresszusatz', 'plz', 'ort', 'land', 'strasse2', 'adresszusatz2', 'plz2', 'ort2', 'land2', 'telefon', 'mobil', 'homepage', 'sprachen', 'hobbys', 'interessen', 'studienort', 'studienfach', 'unityp', 'schwerpunkt', 'nebenfach', 'abschluss', 'zweitstudium', 'hochschulaktivitaeten', 'stipendien', 'auslandsaufenthalte', 'praktika', 'beruf', 'aufgabe_sonstiges_beschreibung'];

// Liste der vom Mitglied änderbaren Booleans
const bearbeiten_bool_ungeprueft = ['sichtbarkeit_geschlecht', 'sichtbarkeit_email', 'sichtbarkeit_geburtstag', 'sichtbarkeit_mensa_nr', 'sichtbarkeit_strasse', 'sichtbarkeit_adresszusatz', 'sichtbarkeit_plz_ort', 'sichtbarkeit_land', 'sichtbarkeit_telefon', 'sichtbarkeit_mobil', 'sichtbarkeit_beschaeftigung', 'sichtbarkeit_studienort', 'sichtbarkeit_studienfach', 'sichtbarkeit_unityp', 'sichtbarkeit_schwerpunkt', 'sichtbarkeit_nebenfach', 'sichtbarkeit_abschluss', 'sichtbarkeit_zweitstudium', 'sichtbarkeit_hochschulaktivitaeten', 'sichtbarkeit_stipendien', 'sichtbarkeit_auslandsaufenthalte', 'sichtbarkeit_praktika', 'sichtbarkeit_beruf', 'auskunft_studiengang', 'auskunft_stipendien', 'auskunft_auslandsaufenthalte', 'auskunft_praktika', 'auskunft_beruf', 'mentoring', 'aufgabe_ma', 'aufgabe_orte', 'aufgabe_vortrag', 'aufgabe_koord', 'aufgabe_graphisch', 'aufgabe_computer', 'aufgabe_texte_schreiben', 'aufgabe_texte_lesen', 'aufgabe_vermittlung', 'aufgabe_ansprechpartner', 'aufgabe_hilfe', 'aufgabe_sonstiges'];

// Liste der von der Mitgliederverwaltung änderbaren Strings
const bearbeiten_strings_admin = ['vorname', 'nachname', 'geschlecht'];

require_once '../lib/base.inc.php';
require_once 'resizeImage.inc.php';

Auth::intern();

Tpl::set('htmlTitle', 'Mein Profil');
Tpl::set('navId', 'bearbeiten');
Tpl::sendHead();

ensure($_REQUEST['id'], ENSURE_INT_GT, 0, Auth::getUID());

$m = laden($_REQUEST['id']);

if (!Auth::ist($m->get('id')) && !Auth::hatRecht('mvedit')) {
    die('Fehlende Berechtigung');
}

// als Funktion, weil es zweimal hier gebraucht wird
function laden(int $uid)
{
    $m = Mitglied::lade($uid, Auth::hatRecht('mvedit'));

    if ($m === null) {
        die('ID ungültig');
    }

    foreach (array_keys(Mitglied::felder) as $feld) {
        Tpl::set($feld, $m->get($feld));
    }
    return $m;
}

$changerUserId = Auth::getUID();

// wenn irgendein Feld (z.B. E-Mail) gesendet wurde, soll gespeichert werden
if (isset($_REQUEST['email'])) {
    foreach (bearbeiten_strings_ungeprueft as $key) {
        ensure($_REQUEST[$key], ENSURE_STRING);
        $m->set($key, $_REQUEST[$key], $changerUserId);
        Tpl::set($key, $_REQUEST[$key]);
    }

    foreach (bearbeiten_bool_ungeprueft as $key) {
        ensure($_REQUEST[$key], ENSURE_BOOL);
        $m->set($key, $_REQUEST[$key], $changerUserId);
        Tpl::set($key, $_REQUEST[$key]);
    }

    $key = 'beschaeftigung';
    ensure($_REQUEST[$key], ENSURE_STRING);
    if (!preg_match('/^(Schueler|Hochschulstudent|Doktorand|Berufstaetig|Sonstiges)$/', $_REQUEST[$key])) {
        die("Wert für $key ungültig.");
    }
    $m->set($key, $_REQUEST[$key], $changerUserId);
    Tpl::set($key, $_REQUEST[$key]);

    // Passwort ändern
    ensure($_REQUEST['new_password'], ENSURE_SET); // nicht ENSURE_STRING, weil dabei ein trim() durchgeführt wird
    $_REQUEST['new_password'] = (string) $_REQUEST['new_password'];
    ensure($_REQUEST['new_password2'], ENSURE_SET);
    $_REQUEST['new_password2'] = (string) $_REQUEST['new_password2'];
    ensure($_REQUEST['new_password2'], ENSURE_SET);
    $_REQUEST['password'] = (string) $_REQUEST['password'];

    if ($_REQUEST['new_password'] !== '' && $_REQUEST['new_password2'] === '' && $_REQUEST['password'] === '' && Auth::checkPassword($_REQUEST['new_password'], $m->get('id'))) {
        // nichts tun. Der Passwort-Manager des Users hat das Passwort eingefügt und autocomplete=new-password ignoriert
    } elseif ($_REQUEST['new_password'] !== '') {
        Tpl::set('set_new_password', true);
        if ($_REQUEST['new_password'] !== $_REQUEST['new_password2']) {
            Tpl::set('new_password2_error', true);
        } else {
            // Die Benutzerverwaltung darf Passwörter ohne Angabe des eigenen Passworts ändern, außer das eigene
            if (Auth::hatRecht('mvedit') && !Auth::ist($m->get('id'))) {
                $m->set('password', $_REQUEST['new_password'], $changerUserId);
            } elseif (Auth::checkPassword($_REQUEST['password'])) {
                $m->set('password', $_REQUEST['new_password'], $changerUserId);
            } else {
                Tpl::set('old_password_error', true);
            }
        }
    }

    $key = 'email';
    ensure($_REQUEST[$key], ENSURE_STRING);
    Tpl::set($key, $_REQUEST[$key]);
    if (!preg_match('/^[a-zA-Z0-9_+&*-]+(?:\.[a-zA-Z0-9_+&*-]+)*@(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,50}$/', $_REQUEST[$key])) { // siehe https://www.owasp.org/index.php/OWASP_Validation_Regex_Repository
        Tpl::set('email_error', true);
    // Änderung der E-Mail einleiten
    } elseif ($m->get('email') !== $_REQUEST['email']) {
        Tpl::set('email_auth_info', true);
        $token = Token::encode([
            time(),
            $m->get('id'),
            $_REQUEST['email']
        ], $m->get('email'), getenv('TOKEN_KEY'));
        Tpl::set('fullName', $m->get('fullName'));
        Tpl::set('token', $token);
        $text = Tpl::render('mails/email-auth', false);
        EmailService::getInstance()->send($_REQUEST['email'], 'E-Mail-Änderung', $text);
    }

    // nur für die Mitgliederverwaltung
    if (Auth::hatRecht('mvedit')) {
        foreach (bearbeiten_strings_admin as $key) {
            ensure($_REQUEST[$key], ENSURE_STRING);
            $m->set($key, $_REQUEST[$key], $changerUserId);
            Tpl::set($key, $_REQUEST[$key]);
        }

        ensure($_REQUEST['delete'], ENSURE_BOOL);
        if ($_REQUEST['delete']) {
            $fehler = $m->delete();
            if ($fehler)  {
                Tpl::set('errorMessage', $fehler);
            }
            Tpl::render('MitgliedController/deleted');
            Tpl::submit();
            exit;
        }

        foreach (['geburtstag', 'aufnahmedatum'] as $key) {
            if (!isset($_REQUEST[$key])) {
                continue;
            }
            ensure($_REQUEST[$key], ENSURE_STRING);
            if (!preg_match('/^([0-9]{4}-[0-9]{1,2}-[0-9]{1,2})?$/', $_REQUEST[$key]) && $_REQUEST[$key]) {
                die("Wert für $key ungültig.");
            }
            if (!$_REQUEST[$key]) {
                $_REQUEST[$key] = null;
            }
            $m->set($key, $_REQUEST[$key], $changerUserId);
            Tpl::set($key, $_REQUEST[$key]);
        }

        $key = 'geschlecht';
        ensure($_REQUEST[$key], ENSURE_STRING);
        if (!preg_match('/^[mwud]$/', $_REQUEST[$key])) {
            die("Wert für $key ungültig.");
        }
        $m->set($key, $_REQUEST[$key], $changerUserId);
        Tpl::set($key, $_REQUEST[$key]);
    }

    // neues Profilbild
    if (isset($_FILES['profilbild']) && $_FILES['profilbild']['error'] === UPLOAD_ERR_OK) {
        // zuerst versuchen, den Dateityp zu ermitteln
        $type = null;
        switch ($_FILES['profilbild']['type']) {
        case 'image/jpeg':
            $type = 'jpeg';
            break;
        case 'image/png':
            $type = 'png';
            break;
        }
        if ($type === null && preg_match('/\.jpe?g$/i', $_FILES['profilbild']['name'])) {
            $type = 'jpeg';
        } elseif ($type === null && preg_match('/\.png$/i', $_FILES['profilbild']['name'])) {
            $type = 'png';
        }
        if ($type) {
            // Dateiname zufällig wählen
            $fileName = $m->get('id') . '-' . urlencode(Password::randomString(16)) . '.' . $type;

            // Datei und Thumbnail erstellen
            list($size_x, $size_y) = resizeImage($_FILES['profilbild']['tmp_name'], 'profilbilder/' . $fileName, $type, Config::profilbildMaxWidth, Config::profilbildMaxHeight);
            resizeImage($_FILES['profilbild']['tmp_name'], 'profilbilder/thumbnail-' . $fileName, $type, Config::thumbnailMaxWidth, Config::thumbnailMaxHeight);

            // altes Profilbild löschen
            if ($m->get('profilbild') && is_file('profilbilder/' . $m->get('profilbild'))) {
                unlink('profilbilder/' . $m->get('profilbild'));
                unlink('profilbilder/thumbnail-' . $m->get('profilbild'));
            }

            $m->set('profilbild', $fileName, $changerUserId);
            $m->set('profilbild_x', $size_x, $changerUserId);
            $m->set('profilbild_y', $size_y, $changerUserId);
        } else {
            Tpl::set('profilbild_format_unbekannt', true);
        }
    }

    // Profilbild löschen
    ensure($_REQUEST['bildLoeschen'], ENSURE_BOOL);
    if ($_REQUEST['bildLoeschen']) {
        // altes Profilbild löschen
        if ($m->get('profilbild') && is_file('profilbilder/' . $m->get('profilbild'))) {
            unlink('profilbilder/' . $m->get('profilbild'));
            unlink('profilbilder/thumbnail-' . $m->get('profilbild'));
        }
        $m->set('profilbild', '', $changerUserId);
    }

    // Rechte aktualisieren
    if (Auth::hatRecht('rechte')) {
        ensure($_REQUEST['rechte'], ENSURE_STRING);
        $_REQUEST['rechte'] = trim($_REQUEST['rechte'], ", \n\r\t\v\0");
        $_REQUEST['rechte'] = preg_replace('/\s+/', ',', $_REQUEST['rechte']);
        $_REQUEST['rechte'] = preg_replace('/,+/', ',', $_REQUEST['rechte']);
        $rechte = $_REQUEST['rechte'] ? array_unique(explode(',', $_REQUEST['rechte'])) : [];

        if (Auth::ist($m->get('id')) && (!in_array('rechte', $rechte, true))) {
            die('Du kannst dir das Recht zur Rechtverwaltung nicht selbst entziehen.');
        }

        try {
            $m->setRoles($rechte);
        } catch (\Exception $e) {
            Tpl::set('errorMessage', 'Beim Setzen der Rollen ist ein Fehler aufgetreten.');
        }
    }

    // Austritt erklären
    if (!empty($_POST['resignPassword'])) {
        if (!Auth::ist($m->get('id'))) {
            die("Der Austritt kann nur durch das Mitglied selbst erklärt werden.");
        }
        if (!Auth::checkPassword($_REQUEST['resignPassword'])) {
            Tpl::set('errorMessage', 'Das eingebene Passwort ist nicht korrekt.');
        } else {
            $m->set('resignation', 'now');
        }
    }

    // Speichern
    $m->set('db_modified', 'now', $changerUserId);
    $m->set('db_modified_user_id', $changerUserId, $changerUserId);
    Tpl::set('data_saved_info', true);
    $m->save();

    // und neu laden (insb. beim Löschen wichtig, sonst müssten alls Keys einzeln zurückgesetzt werden)
    $m = laden($m->get('id'));
}

Tpl::set('roles', $m->getRoles());
Tpl::set('db_modified_user', Mitglied::lade((int)$m->get('db_modified_user_id')), false);

Tpl::render('MitgliedController/bearbeiten');

Tpl::submit();
