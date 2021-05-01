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

die("Herzlichen Willkommen im MHN! Im Mai 2021 haben wir den Aufnahmeprozess in unser Netzwerk umstrukturiert. Ältere inaktive Mitgliedskonten können leider nicht mehr über diesen Link aktiviert werden. Bitte wende dich an webteam@mind-hochschul-netzwerk.de, dann werden wir deinen Zugang manuell freischalten.");

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
