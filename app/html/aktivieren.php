<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Aktiviert ein Mitglied
 * - Fragt die Nutzungsbedingungen ab
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
} else {
    die("forbidden");
}

die("Dein Zugang ist deaktiviert. Bitte wende dich an die Mitgliederbetreuung.");

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

// neu Laden
Tpl::pause();
header('Location: /');
exit;
