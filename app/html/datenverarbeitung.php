<?php
declare(strict_types=1);
namespace MHN\Mitglieder;

/**
 * Kenntnisnahme zur Datenverarbeitung
 *
 * @author Henrik Gebauer <mensa@henrik-gebauer.de>
 * @license https://creativecommons.org/publicdomain/zero/1.0/ CC0 1.0
 */

use MHN\Mitglieder\Auth;
use MHN\Mitglieder\DB;
use MHN\Mitglieder\Mitglied;
use MHN\Mitglieder\Tpl;

require_once '../lib/base.inc.php';

Auth::intern();

Tpl::set('title', 'Verarbeitung personenbezogener Daten');
Tpl::set('htmlTitle', 'Verarbeitung personenbezogener Daten');
Tpl::set('navId', 'datenschutz');
Tpl::sendHead();

ensure($_REQUEST['id'], ENSURE_INT_GT, 0, Auth::getUID());

$m = laden($_REQUEST['id']);

if (!Auth::ist($m->get('id'))) {
    die('Fehlende Berechtigung');
}

// als Funktion, weil es zweimal hier gebraucht wird
function laden(int $uid)
{
    $m = Mitglied::lade($uid);

    if ($m === null) {
        die('ID ungültig');
    }

    return $m;
}

// wenn irgendein Feld (z.B. E-Mail) gesendet wurde, soll gespeichert werden
if (isset($_REQUEST['submit'])) {
    if (!empty($_REQUEST['kenntnisnahme_datenverarbeitung'])) {
        $m->set('kenntnisnahme_datenverarbeitung', 'now');
        $m->set('kenntnisnahme_datenverarbeitung_text', Tpl::render('Datenschutz/kenntnisnahme-text', false));
        $m->save();
        $m = laden($m->get('id'));
    }
}

Tpl::set('kenntnisnahme_datenverarbeitung', $m->get('kenntnisnahme_datenverarbeitung'));
Tpl::set('kenntnisnahme_datenverarbeitung_text', $m->get('kenntnisnahme_datenverarbeitung_text'));

Tpl::render('Datenschutz/form');

Tpl::submit();
