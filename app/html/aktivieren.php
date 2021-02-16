<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Aktiviert ein Mitglied
 * - Fragt die Nutzungsbedingungen ab
 *
 * Bei Neumitgliedern zusätzlich:
 * - Legt den Benutzernamen fest
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\Tpl;

require_once '../lib/base.inc.php';

Tpl::set('htmlTitle', 'Benutzerkonto aktivieren');
Tpl::set('title', 'Benutzerkonto aktivieren');
Tpl::set('navId', 'start');

// Ist das Mitglied bereits eingeloggt, aber muss den Nutzungsbedingungen noch zustimmen?
if (Auth::istEingeloggt()) {
    $u = Mitglied::lade(Auth::getUID(), true);
// Oder ist das Mitglied über ein Login-Token hierher gelangt?
} else {
    ensure($_REQUEST['token'], ENSURE_STRING);
    $id = (int)DB::query('SELECT id FROM mitglieder WHERE new_email_token="%s" AND deleted = false AND new_email_token_expire_time > NOW()', $_REQUEST['token'])->get();
    $u = Mitglied::lade($id, true);
    if ($u === null) {
        die('Token ungültig.');
    }
    Auth::login($id);
}

// Schritt 1: Nutzungsbedingungen müssen akzeptiert werden.
if (!$u->get('aktiviert')) {
    ensure($_REQUEST['input-terms'], ENSURE_BOOL);
    if ($_REQUEST['input-terms']) {
        $u->set('aktiviert', true);
        $u->save();
        Auth::logIn($u->get('id')); // Status neu laden
    } else {
        Tpl::render('Auth/terms-form');
        exit;
    }
}

// Schritt 2: ggf. muss ein Benutzername festgelegt werden
if ($u->get('username') === '') {
    // neuen Benutzernamen als Vorschlag generieren
    $username0 = substr(ucfirst(strtolower(str_replace(' ', '.', trim($u->get('vorname'))))) . '.' . ucfirst(strtolower(str_replace(' ', '.', trim($u->get('nachname'))))), 0, 200);
    $username0 = preg_replace_callback('/\\.([a-z])/', function ($matches) {return '.' . ucfirst($matches[1]);}, $username0);
    $username0 = strtr($username0, [
        'Ä' => 'Ae',
        'Ö' => 'Oe',
        'Ü' => 'Ue',
        'ä' => 'ae',
        'ö' => 'oe',
        'ü' => 'ue',
        'ß' => 'ss',
        'ä' => 'ae',
        'é' => 'e',
        'ç' => 'c',
        'ǧ' => 'g',
        "'" => '-',
    ]);
    $username0 = preg_replace('/[^a-zA-Z0-9\-_\.]/', '.', $username0);
    $username = $username0;

    $n = 1;
    while (Mitglied::getIdByUsername($username) !== null) {
        $username = $username0 . '.' . $n;
        ++$n;
    }

    Tpl::set('username', $username);

    // Hat der Benutzer schon einen Namen vorgeschlagen?
    ensure($_REQUEST['username'], ENSURE_STRING);
    if ($_REQUEST['username']) {
        Tpl::set('username', $_REQUEST['username']);

        // Enthält der Name ungültige Zeichen?
        if (!preg_match('/^[^0-9@:\\/"&#\\?\\\\\\[\\]<>,|;\'!$\\(\\)=+%\*\n\r\t\s_][^@:\\/"&#\\?\\\\\\[\\]<>,|;\'!$\\(\\)=+%\*\n\r\t\s_]*$/', $_REQUEST['username'])) {
            Tpl::set('username_zeichen', true);
        // Gibt es den Namen schon?
        } elseif (!$u->setUsername($_REQUEST['username'])) {
            Tpl::set('username_existiert', true);
        // Ansonsten wird der Name gesetzt!
        } else {
            // Erfolgreich gesetzt (mit setUsername)
            $u->cancelEmailAuth(); // new_email_token leeren
            $u->save();
            Auth::logIn($u->get('id')); // Status neu laden

            $showError = false;

            // ggf. weitere Erst-Aktivierungsaktionen.
            // Wenn ein Fehler dabei auftritt: $showError = true setzen

            // Benutzer wurde initialisiert, aber es gab Fehler
            if ($showError) {
                Tpl::render('Auth/aktiviert');
                exit;
            }
        }
    }

    // Gibt es immer noch keinen Benutzernamen? Dann Formular anzeigen
    if ($u->get('username') === '') {
        Tpl::render('Auth/username');
        exit;
    }
}

// neu Laden
Tpl::pause();
header('Location: /');
exit;
